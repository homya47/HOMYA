<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_redirects_to_french(): void
    {
        $this->get('/')->assertRedirect('/fr');
    }

    public function test_each_supported_language_loads_without_database(): void
    {
        DB::shouldReceive('connection')->never();

        foreach (['fr', 'en', 'de'] as $locale) {
            $this->get('/'.$locale)
                ->assertOk()
                ->assertSee('lang="'.$locale.'"', false)
                ->assertSee('HOMYA');
        }
    }

    public function test_unknown_language_returns_not_found(): void
    {
        $this->get('/es')->assertNotFound();
    }

    public function test_health_endpoint_does_not_depend_on_database(): void
    {
        DB::shouldReceive('connection')->never();

        $this->get('/up')->assertOk();
    }

    public function test_database_diagnostic_refuses_a_different_driver_without_connecting(): void
    {
        app()->detectEnvironment(fn () => 'local');
        config(['database.default' => 'mysql']);
        DB::shouldReceive('connection')->never();

        $this->artisan('homya:db-check')->assertExitCode(1);
    }

    public function test_database_diagnostic_refuses_an_overriding_url_without_connecting(): void
    {
        app()->detectEnvironment(fn () => 'local');
        config(['database.connections.mariadb.url' => 'mysql://example.invalid']);
        DB::shouldReceive('connection')->never();

        $this->artisan('homya:db-check')->assertExitCode(1);
    }

    public function test_database_diagnostic_refuses_a_foreign_socket_without_connecting(): void
    {
        app()->detectEnvironment(fn () => 'local');
        config(['database.connections.mariadb.unix_socket' => '/tmp/mysql.sock']);
        DB::shouldReceive('connection')->never();

        $this->artisan('homya:db-check')->assertExitCode(1);
    }
}
