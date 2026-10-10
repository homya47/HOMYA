<?php

namespace Tests\Feature;

use App\Services\CatalogueCapsule;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\CatalogueCapsuleFixture;
use Tests\TestCase;

class CatalogueCapsuleCommandTest extends TestCase
{
    public function test_default_command_only_simulates_on_fixture_without_database(): void
    {
        $fixture = new CatalogueCapsuleFixture;
        try {
            app()->detectEnvironment(fn () => 'local');
            app()->instance(CatalogueCapsule::class, new CatalogueCapsule($fixture->root));
            DB::shouldReceive('connection')->never();
            DB::shouldReceive('connectUsing')->never();
            $this->artisan('homya:catalogue-capsule')->expectsOutputToContain('SIMULATION_NO_COPY_NO_SQL')->assertExitCode(0);
            $this->assertFalse(file_exists($fixture->parent()));
        } finally {
            $fixture->destroy();
        }
    }

    public function test_command_refuses_production_and_conflicting_modes_before_reading(): void
    {
        $service = \Mockery::mock(CatalogueCapsule::class);
        $service->shouldNotReceive('inspect');
        $service->shouldNotReceive('copy');
        $service->shouldNotReceive('verify');
        app()->instance(CatalogueCapsule::class, $service);
        app()->detectEnvironment(fn () => 'production');
        $this->artisan('homya:catalogue-capsule', ['--copy' => true])->assertExitCode(1);
        app()->detectEnvironment(fn () => 'local');
        $this->artisan('homya:catalogue-capsule', ['--copy' => true, '--verify' => str_repeat('a', 64)])->assertExitCode(1);
    }

    public function test_exception_details_are_not_logged_or_displayed_by_command(): void
    {
        $service = \Mockery::mock(CatalogueCapsule::class);
        $service->shouldReceive('inspect')->once()->andThrow(new RuntimeException('fixture-secret-never-output'));
        app()->instance(CatalogueCapsule::class, $service);
        app()->detectEnvironment(fn () => 'local');
        $this->artisan('homya:catalogue-capsule')->doesntExpectOutputToContain('fixture-secret-never-output')->assertExitCode(1);
    }

    public function test_copy_requires_a_simulated_key_before_reading_files(): void
    {
        $service = \Mockery::mock(CatalogueCapsule::class);
        $service->shouldNotReceive('copy');
        app()->instance(CatalogueCapsule::class, $service);
        app()->detectEnvironment(fn () => 'local');
        $this->artisan('homya:catalogue-capsule', ['--copy' => true])->assertExitCode(1);
    }
}
