<?php

namespace Tests\Feature;

use App\Services\LocalCatalogueReadGuard;
use Illuminate\Database\MariaDbConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CatalogueCapsuleFixture;
use Tests\TestCase;

class Op10LocalReadGuardTest extends TestCase
{
    private CatalogueCapsuleFixture $fixture;

    private $socket;

    private string $access;

    private string $text;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = new CatalogueCapsuleFixture;
        $path = $this->fixture->root.'/socket';
        $this->socket = stream_socket_server('unix://'.$path, $errno, $error);
        $this->assertIsResource($this->socket);
        config(['homya.xampp_socket' => $path, 'database.connections.homya_local_reader.unix_socket' => $path, 'database.connections.mariadb.url' => null]);
        app()->detectEnvironment(fn () => 'local');
        $this->app->instance('request', Request::create('http://127.0.0.1:8000/fr', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']));
        $this->app->setBasePath($this->fixture->root);
        $this->text = "DB_CONNECTION=mariadb\nDB_HOST=127.0.0.1\nDB_PORT=3307\nDB_SOCKET=$path\nDB_DATABASE=homya_local\nDB_USERNAME=homya_local_reader\nDB_PASSWORD=".str_repeat('a', 64)."\nDB_CHARSET=utf8mb4\nDB_COLLATION=utf8mb4_bin\n";
        $this->access = 'storage/app/private/database-access/20261007T222611Z-9710d513-f606-49e5-a2a3-0d28926b3307/.env.homya_local_reader';
        $this->fixture->write($this->access, $this->text);
        chmod($this->fixture->root.'/'.$this->access, 0600);
    }

    protected function tearDown(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->fixture->destroy();
        parent::tearDown();
    }

    public static function invalidContexts(): array
    {
        return [['production', '127.0.0.1', '127.0.0.1', null], ['testing', '127.0.0.1', '127.0.0.1', null],
            ['local', 'site.example', '127.0.0.1', null], ['local', '127.0.0.1', '192.168.1.2', null],
            ['local', '127.0.0.1', '127.0.0.1', 'Forwarded'], ['local', '127.0.0.1', '127.0.0.1', 'X-Forwarded-For'],
            ['local', '127.0.0.1', '127.0.0.1', 'X-Forwarded-Host']];
    }

    #[DataProvider('invalidContexts')]
    public function test_nonlocal_and_proxy_requests_are_refused_without_pdo(string $env, string $host, string $remote, ?string $header): void
    {
        DB::shouldReceive('connectUsing')->never();
        DB::shouldReceive('connection')->never();
        app()->detectEnvironment(fn () => $env);
        $request = Request::create('http://'.$host.'/fr', 'GET', server: ['REMOTE_ADDR' => $remote]);
        if ($header !== null) {
            $request->headers->set($header, '127.0.0.1');
        }
        $this->app->instance('request', $request);
        $this->expectException(RuntimeException::class);
        (new LocalCatalogueReadGuard)->connect();
    }

    public function test_cached_configuration_is_refused(): void
    {
        $this->app->useBootstrapPath($this->fixture->root.'/bootstrap');
        $this->fixture->write('bootstrap/cache/config.php', '<?php return [];');
        DB::shouldReceive('connectUsing')->never();
        $this->expectExceptionMessage('LOCAL_READER_CONTEXT_REQUIRED');
        (new LocalCatalogueReadGuard)->connect();
    }

    public static function targets(): array
    {
        return [['username', 'root'], ['username', 'homya_local_import'], ['username', 'homya_test_reader'],
            ['database', 'homya_test'], ['port', '3306'], ['url', 'mysql://example.invalid'],
            ['options', [\PDO::ATTR_PERSISTENT => true]], ['read', ['host' => 'example.invalid']], ['unix_socket', '']];
    }

    #[DataProvider('targets')]
    public function test_wrong_role_target_or_options_are_refused(string $key, mixed $value): void
    {
        DB::shouldReceive('connectUsing')->never();
        $config = config('database.connections.homya_local_reader');
        $config[$key] = $value;
        $this->expectException(RuntimeException::class);
        (new LocalCatalogueReadGuard)->configurationFromText($this->text, $config);
    }

    public static function privateChanges(): array
    {
        return [['file'], ['directory'], ['symlink'], ['hardlink']];
    }

    #[DataProvider('privateChanges')]
    public function test_unsafe_access_metadata_is_refused_without_pdo(string $change): void
    {
        DB::shouldReceive('connectUsing')->never();
        $path = $this->fixture->root.'/'.$this->access;
        match ($change) {
            'file' => chmod($path, 0644),
            'directory' => chmod(dirname($path), 0755),
            'hardlink' => link($path, $this->fixture->root.'/hardlink'),
            'symlink' => (function () use ($path) {
                rename($path, $path.'.original');
                symlink($path.'.original', $path);
            })(),
        };
        $this->expectException(RuntimeException::class);
        (new LocalCatalogueReadGuard)->connect();
    }

    public function test_synthetic_reader_is_loaded_in_memory_and_identity_checked_without_default_connection(): void
    {
        $conn = new class extends MariaDbConnection
        {
            public bool $checked = false;

            public function __construct()
            {
                parent::__construct(fn () => throw new RuntimeException('REAL_PDO_FORBIDDEN'));
            }

            public function selectOne($query, $bindings = [], $useReadPdo = true)
            {
                $this->checked = true;

                return (object) ['identity' => 'homya_local_reader@localhost', 'database_name' => 'homya_local',
                    'version' => config('homya.xampp_version'), 'socket' => config('homya.xampp_socket'), 'datadir' => config('homya.xampp_datadir'),
                    'port' => '3307', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin', 'packet_global' => 16777216, 'packet_session' => 16777216,
                    'foreign_keys' => 1, 'checks' => 1, 'sql_mode' => 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'];
            }
        };
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->once()->with('homya_catalogue_web_reader', \Mockery::on(fn ($c) => $c['username'] === 'homya_local_reader' && $c['password'] === str_repeat('a', 64)), true)->andReturn($conn);
        $this->assertSame($conn, (new LocalCatalogueReadGuard)->connect());
        $this->assertTrue($conn->checked);
        $this->assertSame('', config('database.connections.homya_local_reader.password'));
        $this->expectExceptionMessage('READER_RECONNECT_FORBIDDEN');
        $conn->reconnect();
    }

    public function test_connection_failure_is_purged_and_private_exception_is_not_chained(): void
    {
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->once()->andThrow(new RuntimeException('SYNTHETIC_SECRET'));
        DB::shouldReceive('purge')->once()->with('homya_catalogue_web_reader');
        try {
            (new LocalCatalogueReadGuard)->connect();
            $this->fail('Expected safe failure');
        } catch (RuntimeException $error) {
            $this->assertSame('LOCAL_READER_UNAVAILABLE', $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
    }
}
