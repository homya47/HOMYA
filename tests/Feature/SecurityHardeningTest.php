<?php

namespace Tests\Feature;

use App\Services\ProductionSecurity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

final class SecurityHardeningTest extends TestCase
{
    private string $temporary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        DB::shouldReceive('connection')->never();
        $this->temporary = sys_get_temp_dir().'/homya-security-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->temporary.'/sessions', 0700);
        $this->app->useStoragePath($this->temporary.'/storage');
        File::ensureDirectoryExists(storage_path('app/private'), 0700);
        File::ensureDirectoryExists(storage_path('logs'), 0700);
        $this->app->usePublicPath($this->temporary.'/public');
        File::ensureDirectoryExists(public_path());
        Route::middleware('web')->get('/security-fixture', fn () => response('Fixture'))->name('security.fixture');
        Route::middleware('web')->get('/security-exception', fn () => throw new RuntimeException('PRIVATE_SENTINEL SQL secret password'));
        Route::middleware('web')->post('/security-validation', fn () => request()->validate(['required_value' => 'required']));
        Route::get('/security-sandbox', fn () => response('Fixture')->header('Content-Security-Policy', "default-src 'none'; sandbox"));
        $this->app['env'] = 'production';
        config(['app.debug' => false, 'app.url' => 'https://homya.eu', 'session.secure' => true,
            'session.http_only' => true, 'session.encrypt' => true, 'session.domain' => null,
            'session.driver' => 'file', 'session.files' => $this->temporary.'/sessions', 'session.same_site' => 'lax']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporary);
        parent::tearDown();
    }

    public function test_safe_production_serves_https_and_protects_cookie_and_browser(): void
    {
        $this->assertSame([], app(ProductionSecurity::class)->failures());
        $response = $this->get('https://homya.eu/security-fixture')->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertNull($cookie->getDomain());
        $this->assertStringContainsString("object-src 'none'", $response->headers->get('Content-Security-Policy'));
        $next = $this->get('https://homya.eu/security-fixture')->assertOk();
        $this->assertNotSame($response->headers->get('Content-Security-Policy'), $next->headers->get('Content-Security-Policy'));
    }

    public function test_unsafe_production_configuration_fails_closed_before_route_execution(): void
    {
        foreach (['app.debug' => true, 'app.url' => 'http://homya.eu', 'app.key' => '',
            'session.secure' => false, 'session.http_only' => false, 'session.encrypt' => false,
            'session.same_site' => 'none', 'session.domain' => '.homya.eu', 'session.driver' => 'array',
            'session.serialization' => 'php', 'filesystems.disks.local.serve' => true,
            'logging.channels.single.permission' => 0644, 'logging.channels.daily.permission' => 0644,
            'logging.channels.monthly.permission' => 0644] as $key => $invalid) {
            $before = config($key);
            config([$key => $invalid]);
            $this->get('https://homya.eu/security-fixture')->assertStatus(503)
                ->assertDontSee('Fixture')->assertHeader('Cache-Control', 'no-store, private');
            config([$key => $before]);
        }
        File::put(public_path('hot'), 'http://localhost:5173');
        $this->get('https://homya.eu/security-fixture')->assertStatus(503)->assertDontSee('5173');
    }

    public function test_foreign_host_plain_http_and_forged_proxy_headers_are_rejected(): void
    {
        $this->get('https://foreign.example/security-fixture')->assertStatus(400)->assertDontSee('Fixture');
        $this->get('http://homya.eu/security-fixture')->assertStatus(426)->assertHeaderMissing('Strict-Transport-Security');
        $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'homya.eu'])
            ->get('http://foreign.example/security-fixture')->assertStatus(400)->assertDontSee('Fixture');
    }

    public function test_production_exception_does_not_disclose_secrets_in_response_or_logs(): void
    {
        Log::shouldReceive('error')->once()->with('HOMYA_PRODUCTION_EXCEPTION', \Mockery::on(fn ($context) => $context['type'] === RuntimeException::class && array_keys($context) === ['type', 'source', 'line']
            && ! str_contains(json_encode($context), 'PRIVATE_SENTINEL')));
        $this->get('https://homya.eu/security-exception')->assertStatus(500)
            ->assertDontSee('PRIVATE_SENTINEL')->assertDontSee('SQL secret')->assertDontSee('SecurityHardeningTest');
    }

    public function test_validation_never_flashes_card_credentials_or_access_secrets(): void
    {
        $secrets = array_fill_keys(['card_number', 'cardholder', 'expiry', 'expiry_month', 'expiry_year', 'cvc', 'cvv', 'iban', 'bic', 'password', 'code', 'body', 'access_token'], 'PRIVATE_SENTINEL');
        $response = $this->withSession(['_token' => 'fixture-csrf'])->from('https://homya.eu/security-fixture')
            ->post('https://homya.eu/security-validation', $secrets + ['_token' => 'fixture-csrf'])->assertSessionHasErrors();
        foreach (array_keys($secrets) as $key) {
            $response->assertSessionMissing('_old_input.'.$key);
        }
    }

    public function test_readiness_command_is_read_only_and_never_prints_configuration_values(): void
    {
        $this->artisan('homya:security-audit --json')->assertSuccessful();
        config(['app.debug' => true]);
        $this->artisan('homya:security-audit')->expectsOutput('FAIL debug_disabled')->assertFailed();
    }

    public function test_stricter_document_sandbox_and_local_admin_boundary_remain_in_effect(): void
    {
        $this->get('https://homya.eu/security-sandbox')->assertOk()
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
        $this->get('https://homya.eu/admin/connexion')->assertNotFound()->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_private_permissions_and_public_link_cannot_expose_internal_storage(): void
    {
        chmod(storage_path('app/private'), 0755);
        clearstatcache();
        $this->get('https://homya.eu/security-fixture')->assertStatus(503);
        chmod(storage_path('app/private'), 0700);
        clearstatcache();
        symlink(storage_path('app/private'), public_path('storage'));
        $this->get('https://homya.eu/security-fixture')->assertStatus(503);
    }

    public function test_remote_request_with_accidental_local_environment_is_blocked(): void
    {
        $this->app['env'] = 'local';
        config(['app.debug' => true]);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])
            ->withHeaders(['X-Forwarded-For' => '127.0.0.1'])
            ->get('https://homya.eu/security-fixture')->assertStatus(503)->assertDontSee('Fixture');
    }

    public function test_production_rejects_a_nonstandard_https_port(): void
    {
        $this->get('https://homya.eu:8443/security-fixture')->assertStatus(400)->assertDontSee('Fixture');
    }

    public function test_loopback_peer_cannot_publish_local_mode_under_a_public_hostname(): void
    {
        $this->app['env'] = 'local';
        config(['app.debug' => true]);
        $this->get('https://homya.eu/security-fixture')->assertStatus(503)->assertDontSee('Fixture');
        $this->get('http://localhost/security-fixture')->assertOk();
    }

    public function test_testing_or_unknown_environment_cannot_be_exposed_to_a_remote_peer(): void
    {
        foreach (['testing', 'staging'] as $environment) {
            $this->app['env'] = $environment;
            config(['app.debug' => true]);
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])->get('https://homya.eu/security-fixture')
                ->assertStatus(503)->assertDontSee('Fixture');
        }
    }

    public function test_early_framework_error_with_local_environment_never_discloses_trace_to_remote_peer(): void
    {
        $this->app['env'] = 'local';
        config(['app.debug' => true]);
        $this->withHeaders(['Accept' => 'application/json'])
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.8', 'CONTENT_LENGTH' => '999999999'])
            ->post('https://homya.eu/security-validation')->assertStatus(413)
            ->assertJsonMissingPath('trace')->assertJsonMissingPath('exception')->assertJsonMissingPath('file');
    }

    public function test_public_or_linked_logs_directory_blocks_production(): void
    {
        chmod(storage_path('logs'), 0755);
        clearstatcache();
        $this->get('https://homya.eu/security-fixture')->assertStatus(503)->assertDontSee('Fixture');
        chmod(storage_path('logs'), 0700);
        rmdir(storage_path('logs'));
        File::ensureDirectoryExists($this->temporary.'/outside-logs', 0700);
        symlink($this->temporary.'/outside-logs', storage_path('logs'));
        clearstatcache();
        $this->get('https://homya.eu/security-fixture')->assertStatus(503)->assertDontSee('Fixture');
    }

    public function test_single_and_rotating_log_channels_create_private_files(): void
    {
        foreach (['single', 'daily', 'monthly'] as $channel) {
            $directory = $this->temporary.'/logger-'.$channel;
            File::ensureDirectoryExists($directory, 0700);
            $logger = Log::build(['driver' => $channel, 'path' => $directory.'/fixture.log',
                'permission' => config('logging.channels.'.$channel.'.permission')]);
            $logger->warning('Synthetic log permission fixture; no customer data.');
            $logger->getLogger()->close();
            $files = File::files($directory);
            $this->assertCount(1, $files);
            $this->assertSame(0600, fileperms($files[0]->getPathname()) & 0777);
        }
    }
}
