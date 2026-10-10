<?php

namespace Tests\Feature;

use App\Services\ArchiveDatabaseGuard;
use App\Services\CatalogueCapsule;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\CatalogueArchiveFixture;
use Tests\TestCase;

class CatalogueArchiveCommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->detectEnvironment(fn () => 'local');
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
        DB::shouldReceive('purge')->never();
    }

    public function test_schema_command_is_offline(): void
    {
        $this->artisan('homya:catalogue-schema')->expectsOutputToContain('DDL_COMPILED_NO_SQL')->assertSuccessful();
    }

    public function test_simulation_verifies_capsule_before_and_after_without_access_loader(): void
    {
        $i = CatalogueArchiveFixture::input();
        $capsule = $this->mock(CatalogueCapsule::class);
        $capsule->shouldReceive('documents')->once()->with($i['key'], null)->andReturn($i + ['inspection' => []]);
        $capsule->shouldReceive('verify')->once()->with($i['key'], null)->andReturn(['lot_key' => $i['key'], 'sql_executed' => false]);
        $this->artisan('homya:catalogue-import', ['lot' => $i['key']])->expectsOutputToContain('IMPORT_SIMULATION_NO_SQL')->assertSuccessful();
    }

    public function test_simulation_rejects_access_before_capsule_or_sql(): void
    {
        $this->mock(CatalogueCapsule::class)->shouldNotReceive('documents');
        $this->artisan('homya:catalogue-import', ['lot' => str_repeat('a', 64), '--access' => '/private/path'])
            ->expectsOutputToContain('Opération interrompue')->assertFailed();
    }

    public function test_exception_details_are_not_printed(): void
    {
        $this->mock(CatalogueCapsule::class)->shouldReceive('documents')->andThrow(new RuntimeException('SECRET_CANARY_SQL_AND_SOURCE'));
        $this->artisan('homya:catalogue-import', ['lot' => str_repeat('a', 64)])
            ->expectsOutputToContain('Aucune activation')->doesntExpectOutputToContain('SECRET_CANARY')->assertFailed();
    }

    public function test_execution_without_explicit_matching_scope_never_connects(): void
    {
        $this->mock(CatalogueCapsule::class)->shouldNotReceive('documents');
        $this->artisan('homya:catalogue-import', ['lot' => str_repeat('a', 64), '--execute' => true,
            '--connection' => 'homya_local_import', '--operation' => 'OP-06', '--expected-lot' => str_repeat('a', 64)])
            ->assertFailed();
    }

    public function test_role_is_rejected_before_private_access_file_is_read(): void
    {
        $this->expectExceptionMessage('CONNECTION_ROLE_INVALID');
        (new ArchiveDatabaseGuard)->configurationForRole('homya_test_reader', '/file/does/not/exist', 'import');
    }

    public function test_schema_refuses_execution_options_in_offline_mode(): void
    {
        $this->artisan('homya:catalogue-schema', ['--access' => '/private/path'])
            ->expectsOutputToContain('avant connexion')->assertFailed();
    }

    public function test_schema_refuses_nonmatching_hash_before_access_or_migrator(): void
    {
        $this->artisan('homya:catalogue-schema', ['--execute' => true, '--operation' => 'OP-05',
            '--connection' => 'homya_test_schema', '--expected-ddl' => str_repeat('a', 64), '--expected-files' => str_repeat('b', 64)])
            ->expectsOutputToContain('avant connexion')->assertFailed();
    }
}
