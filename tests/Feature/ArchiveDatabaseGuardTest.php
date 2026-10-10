<?php

namespace Tests\Feature;

use App\Services\ArchiveDatabaseGuard;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\CatalogueCapsuleFixture;
use Tests\TestCase;

class ArchiveDatabaseGuardTest extends TestCase
{
    private CatalogueCapsuleFixture $fixture;

    private $socket;

    private string $name = 'homya_test_reader';

    private string $access;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = new CatalogueCapsuleFixture;
        $socketPath = $this->fixture->root.'/fixture.sock';
        $this->socket = stream_socket_server('unix://'.$socketPath, $errorCode, $errorMessage);
        $this->assertIsResource($this->socket);
        config(['homya.xampp_socket' => $socketPath, 'database.connections.mariadb.url' => null]);
        foreach (['test', 'local'] as $scope) {
            foreach (['reader', 'import', 'schema'] as $role) {
                config(['database.connections.homya_'.$scope.'_'.$role.'.unix_socket' => $socketPath]);
            }
        }
        $this->app->setBasePath($this->fixture->root);
        config(['homya.database_access_path' => $this->fixture->root.'/storage/app/private/database-access']);
        $this->access = config('homya.database_access_path').'/fixture/.env.'.$this->name;
        $this->fixture->write('storage/app/private/database-access/fixture/.env.'.$this->name,
            "DB_CONNECTION=mariadb\nDB_HOST=127.0.0.1\nDB_PORT=3307\nDB_SOCKET=$socketPath\nDB_DATABASE=homya_test\nDB_USERNAME=$this->name\nDB_PASSWORD=".str_repeat('a', 64)."\nDB_CHARSET=utf8mb4\nDB_COLLATION=utf8mb4_bin\n");
        chmod($this->access, 0600);
        app()->detectEnvironment(fn () => 'local');
    }

    protected function tearDown(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->fixture->destroy();
        parent::tearDown();
    }

    public function test_six_connections_are_fixed_and_contain_no_loaded_passwords(): void
    {
        $guard = new ArchiveDatabaseGuard;
        foreach (['test', 'local'] as $scope) {
            foreach (['reader', 'import', 'schema'] as $role) {
                $name = 'homya_'.$scope.'_'.$role;
                $config = config('database.connections.'.$name);
                $guard->validateConfiguration($name, $config);
                $this->assertSame('homya_'.$scope, $config['database']);
                $this->assertSame('', $config['password']);
                $this->assertNull($config['url']);
                $this->assertSame('3307', $config['port']);
            }
        }
    }

    public static function invalidConfigurations(): array
    {
        return [
            'root' => ['username', 'root'], 'role' => ['username', 'homya_test_schema'],
            'other database' => ['database', 'homya_local'], 'Homebrew' => ['port', '3306'],
            'TCP' => ['unix_socket', ''], 'socket' => ['unix_socket', '/tmp/mysql.sock'],
            'driver' => ['driver', 'mysql'], 'URL' => ['url', 'mysql://fixture.invalid'],
            'charset' => ['charset', 'latin1'], 'collation' => ['collation', 'utf8mb4_unicode_ci'],
            'strict off' => ['strict', false], 'persistent' => ['options', [\PDO::ATTR_PERSISTENT => true]],
            'connection callback' => ['isolation_level', 'arbitrary'], 'read host' => ['read', ['host' => 'foreign']],
        ];
    }

    #[DataProvider('invalidConfigurations')]
    public function test_invalid_configurations_are_refused_before_any_connection(string $key, mixed $value): void
    {
        DB::shouldReceive('connectUsing')->never();
        DB::shouldReceive('connection')->never();
        config(['database.connections.'.$this->name.'.'.$key => $value]);
        $this->artisan('homya:db-check', ['--connection' => $this->name, '--access' => $this->access])->assertExitCode(1);
    }

    public function test_private_access_is_loaded_only_in_memory_and_never_into_configuration(): void
    {
        DB::shouldReceive('connection')->never();
        $configuration = (new ArchiveDatabaseGuard)->configuration($this->name, $this->access);
        $this->assertSame(str_repeat('a', 64), $configuration['password']);
        $this->assertSame('', config('database.connections.'.$this->name.'.password'));
    }

    public function test_external_access_path_is_refused(): void
    {
        $this->expectExceptionMessage('ACCESS_PATH_INVALID');
        (new ArchiveDatabaseGuard)->configuration($this->name, $this->fixture->root.'/.env');
    }

    public function test_world_readable_access_file_is_refused(): void
    {
        chmod($this->access, 0644);
        $this->expectExceptionMessage('PRIVATE_FILE_INVALID');
        (new ArchiveDatabaseGuard)->configuration($this->name, $this->access);
    }

    public function test_world_traversable_access_directory_is_refused(): void
    {
        chmod(dirname($this->access), 0755);
        $this->expectExceptionMessage('PRIVATE_FILE_INVALID');
        (new ArchiveDatabaseGuard)->configuration($this->name, $this->access);
    }

    public function test_access_symlink_is_refused(): void
    {
        rename($this->access, $this->access.'.real');
        symlink($this->access.'.real', $this->access);
        $this->expectException(RuntimeException::class);
        (new ArchiveDatabaseGuard)->configuration($this->name, $this->access);
    }

    public function test_access_acl_is_refused_on_macos(): void
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            $this->markTestSkipped('macOS ACL fixture');
        }
        $process = new Process(['/bin/chmod', '+a', 'everyone allow read', $this->access]);
        $process->mustRun();
        $this->expectExceptionMessage('PRIVATE_ACL_UNVERIFIED');
        (new ArchiveDatabaseGuard)->configuration($this->name, $this->access);
    }

    public function test_access_missing_secret_is_refused_without_exposing_input(): void
    {
        $text = file_get_contents($this->access);
        file_put_contents($this->access, str_replace(str_repeat('a', 64), '', $text));
        $this->expectExceptionMessage('ACCESS_VALUES_INVALID');
        (new ArchiveDatabaseGuard)->configuration($this->name, $this->access);
    }

    public function test_an_overriding_global_url_is_refused_for_named_connections_too(): void
    {
        config(['database.connections.mariadb.url' => 'mysql://fixture.invalid']);
        $this->expectException(RuntimeException::class);
        (new ArchiveDatabaseGuard)->configuration($this->name, $this->access);
    }

    private function server(bool $authOnly): object
    {
        return (object) [
            'identity' => $this->name.'@localhost', 'database_name' => $authOnly ? null : 'homya_test',
            'version' => config('homya.xampp_version'), 'socket' => config('homya.xampp_socket'),
            'datadir' => config('homya.xampp_datadir'), 'port' => '3307', 'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_bin', 'packet_global' => 16777216, 'packet_session' => 16777216,
            'sql_mode' => 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION',
            'foreign_keys' => 1, 'checks' => 1,
        ];
    }

    public static function invalidServers(): array
    {
        return [
            'identity' => ['identity', 'root@localhost'], 'database' => ['database_name', 'homya_local'],
            'version' => ['version', '8.4-MySQL'], 'socket' => ['socket', '/tmp/mysql.sock'],
            'datadir' => ['datadir', '/opt/homebrew/var/mysql/'], 'port' => ['port', 3306],
            'charset' => ['charset', 'latin1'], 'collation' => ['collation', 'utf8mb4_unicode_ci'],
            'global packet' => ['packet_global', 1048576], 'session packet' => ['packet_session', 1048576],
            'mode' => ['sql_mode', ''], 'FK' => ['foreign_keys', 0], 'CHECK' => ['checks', 0],
        ];
    }

    #[DataProvider('invalidServers')]
    public function test_unexpected_server_facts_are_refused_with_doubles(string $key, mixed $value): void
    {
        $server = $this->server(false);
        $server->$key = $value;
        $this->expectException(RuntimeException::class);
        (new ArchiveDatabaseGuard)->validateServer($server, $this->name, false);
    }

    public function test_auth_only_diagnostic_uses_no_database_and_reports_no_readiness(): void
    {
        $connection = \Mockery::mock();
        $connection->shouldReceive('selectOne')->once()->andReturn($this->server(true));
        DB::shouldReceive('connectUsing')->once()->with('homya_archive_diagnostic', \Mockery::on(function ($config) {
            return $config['database'] === '' && $config['password'] === str_repeat('a', 64);
        }), true)->andReturn($connection);
        DB::shouldReceive('purge')->once()->with('homya_archive_diagnostic');
        $this->artisan('homya:db-check', ['--connection' => $this->name, '--access' => $this->access, '--auth-only' => true])
            ->expectsOutput('Authentification XAMPP vérifiée sans base sélectionnée. Droits de lecture et catalogue non vérifiés.')
            ->assertExitCode(0);
        $this->assertSame('', config('database.connections.'.$this->name.'.password'));
    }

    public function test_schema_access_error_is_safely_reported_and_disconnected(): void
    {
        $secret = 'fixture-secret-never-output';
        DB::shouldReceive('connectUsing')->once()->andThrow(new RuntimeException($secret));
        DB::shouldReceive('purge')->once();
        $this->artisan('homya:db-check', ['--connection' => $this->name, '--access' => $this->access])
            ->expectsOutput('Connexion non vérifiée : cible, accès, session ou schéma indisponible. Aucun détail d’accès affiché.')
            ->doesntExpectOutputToContain($secret)->assertExitCode(1);
    }

    public function test_default_runtime_refuses_a_schema_account(): void
    {
        config(['database.connections.mariadb.username' => 'homya_local_schema']);
        DB::shouldReceive('connection')->never();
        $this->artisan('homya:db-check')->assertExitCode(1);
    }
}
