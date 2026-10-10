<?php

namespace Tests\Feature;

use App\Services\CatalogueArchiveSchema;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Tests\Support\ArchiveSchemaConnectionDouble;
use Tests\Support\CatalogueCapsuleFixture;
use Tests\TestCase;

class CatalogueSchemaPreparationTest extends TestCase
{
    private CatalogueCapsuleFixture $fixture;

    private $socket;

    private string $access;

    private string $accessDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->detectEnvironment(fn () => 'local');
        $this->fixture = new CatalogueCapsuleFixture;
        $path = $this->fixture->root.'/db.sock';
        $this->socket = stream_socket_server('unix://'.$path);
        $this->assertIsResource($this->socket);
        config(['homya.xampp_socket' => $path, 'database.connections.mariadb.url' => null,
            'database.connections.homya_test_schema.unix_socket' => $path]);
        $this->accessDirectory = config('homya.database_access_path').'/op05-synthetic-test-'.bin2hex(random_bytes(8));
        mkdir($this->accessDirectory, 0700);
        $this->access = $this->accessDirectory.'/.env.homya_test_schema';
        file_put_contents($this->access, "DB_CONNECTION=mariadb\nDB_HOST=127.0.0.1\nDB_PORT=3307\nDB_SOCKET={$path}\nDB_DATABASE=homya_test\nDB_USERNAME=homya_test_schema\nDB_CHARSET=utf8mb4\nDB_COLLATION=utf8mb4_bin\nDB_PASSWORD=".str_repeat('f', 64)."\n");
        chmod($this->access, 0600);
    }

    protected function tearDown(): void
    {
        if (isset($this->access) && is_file($this->access)) {
            unlink($this->access);
            rmdir($this->accessDirectory);
        }
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->fixture->destroy();
        parent::tearDown();
    }

    private function runCommand(ArchiveSchemaConnectionDouble $db, bool $success): void
    {
        $original = new CatalogueArchiveSchema;
        $this->app->instance(CatalogueArchiveSchema::class, $original);
        $previous = config('database.default');
        $resolver = new ConnectionResolver(['homya_archive_schema_execution' => $db, $previous => $db]);
        $resolver->setDefaultConnection($previous);
        $repository = new DatabaseMigrationRepository($resolver, 'migrations');
        $migrator = new Migrator($repository, $resolver, $this->app['files'], $this->app['events']);
        $this->app->instance('migrator', $migrator);
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->once()->with('homya_archive_schema_execution', \Mockery::type('array'), true)->andReturn($db);
        DB::shouldReceive('purge')->once()->with('homya_archive_schema_execution');
        $command = $this->artisan('homya:catalogue-schema', ['--execute' => true, '--operation' => 'OP-05',
            '--connection' => 'homya_test_schema', '--access' => $this->access,
            '--expected-ddl' => hash('sha256', $original->ddl()), '--expected-files' => $original->fileDigest()])
            ->expectsConfirmation('Autorisation distincte obtenue pour ces seules migrations, journal préparé et droits vérifiés ?', 'yes');
        $success ? $command->expectsOutputToContain('CATALOGUE_MIGRATIONS_COMPLETED')->assertSuccessful()
            : $command->expectsOutputToContain('Migration interrompue')->assertFailed();
        $this->assertSame($original, app(CatalogueArchiveSchema::class));
        $this->assertSame($previous, $resolver->getDefaultConnection());
        $this->assertSame($previous, config('database.default'));
    }

    public function test_actual_migrator_runs_only_four_reviewed_migrations_without_pdo(): void
    {
        $db = new ArchiveSchemaConnectionDouble;
        $this->runCommand($db, true);
        $this->assertSame((new CatalogueArchiveSchema)->ddl(), implode(";\n", $db->ddl).";\n");
        $this->assertSame(array_map(fn ($f) => ['migration' => substr($f, 0, -4), 'batch' => 1], CatalogueArchiveSchema::MIGRATIONS), $db->journal);
        $this->assertSame(['migrations', ...CatalogueArchiveSchema::TABLES], $db->tables);
        $this->assertSame(0, $db->transactionLevel());
    }

    public function test_partial_schema_is_refused_without_ddl_or_journal_write(): void
    {
        $db = new ArchiveSchemaConnectionDouble;
        $db->tables[] = 'archive_lots';
        $this->runCommand($db, false);
        $this->assertSame([], $db->ddl);
        $this->assertSame([], $db->journal);
    }

    public function test_nonempty_journal_is_refused_before_ddl(): void
    {
        $db = new ArchiveSchemaConnectionDouble;
        $db->journal[] = ['migration' => 'existing', 'batch' => 1];
        $this->runCommand($db, false);
        $this->assertSame([], $db->ddl);
        $this->assertCount(1, $db->journal);
    }

    public function test_ddl_failure_keeps_partial_state_and_never_drops_or_retries(): void
    {
        $db = new ArchiveSchemaConnectionDouble;
        $db->failCreate = 'archive_products';
        $this->runCommand($db, false);
        $this->assertSame(['migrations', 'archive_lots', 'archive_categories'], $db->tables);
        $this->assertCount(2, $db->journal);
        $this->assertSame(0, $db->transactionLevel());
    }
}
