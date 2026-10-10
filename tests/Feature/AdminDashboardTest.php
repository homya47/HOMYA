<?php

namespace Tests\Feature;

use App\Services\AdminAccess;
use App\Services\AdminDashboard;
use App\Services\ReturnRequests;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AdminDashboardTest extends TestCase
{
    private string $temporary;

    private string $previousStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->previousStorage = storage_path();
        $this->temporary = sys_get_temp_dir().'/homya-admin-test-'.Str::uuid();
        $this->app->useStoragePath($this->temporary);
        config(['admin.username' => 'fixture-admin', 'admin.password_hash' => Hash::make('fixture-only-password-12345')]);
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->previousStorage);
        DB::purge('commerce');
        File::deleteDirectory($this->temporary);
        parent::tearDown();
    }

    private function authenticated(): array
    {
        return ['admin.auth' => ['fingerprint' => app(AdminAccess::class)->fingerprint(), 'started' => now()->timestamp, 'seen' => now()->timestamp]];
    }

    private function database(): void
    {
        config(['database.connections.commerce' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::purge('commerce');
        (require database_path('migrations/commerce/2026_10_09_000001_create_commerce_tables.php'))->up();
    }

    private function order(string $status, int $amount): string
    {
        $id = (string) Str::uuid();
        DB::connection('commerce')->table('commerce_orders')->insert(['id' => $id, 'owner_hash' => str_repeat('a', 64), 'checkout_key' => Str::uuid(), 'request_hash' => str_repeat('b', 64), 'locale' => 'fr', 'country' => 'FR', 'currency' => 'EUR', 'subtotal_cents' => $amount, 'shipping_cents' => 0, 'total_cents' => $amount, 'customer_encrypted' => Crypt::encryptString(json_encode(['name' => '<script>fixture</script>', 'email' => 'fixture@example.invalid', 'address' => 'Adresse de test', 'city' => 'Test'])), 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    public function test_every_admin_data_route_requires_authentication_and_private_headers(): void
    {
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('purge')->with('commerce')->once();
        foreach (['/admin', '/admin/commandes', '/admin/stocks', '/admin/retours', '/admin/commandes/'.Str::uuid(), '/admin/retours/'.Str::uuid()] as $url) {
            $this->get($url)->assertRedirect('/admin/connexion')->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('X-Frame-Options', 'DENY');
        }
        $this->get('/admin/connexion')->assertOk()->assertSee('Connexion administrateur')->assertDontSee(config('admin.password_hash'))->assertDontSee('fixture-only-password');
    }

    public function test_actual_peer_and_environment_guard_cannot_be_bypassed_by_forwarded_headers(): void
    {
        $this->withSession($this->authenticated())->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->withHeaders(['X-Forwarded-For' => '127.0.0.1'])->get('/admin')->assertNotFound();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->get('http://example.invalid/admin')->assertNotFound();
        $this->app->instance('env', 'production');
        $this->get('/admin/connexion')->assertNotFound();
    }

    public function test_login_logout_rotation_and_timeout_enforce_session_access(): void
    {
        $this->post('/admin/connexion', ['username' => 'fixture-admin', 'password' => 'wrong'])->assertSessionHasErrors('username')->assertSessionMissing('admin.auth');
        $this->assertArrayNotHasKey('password', session('_old_input', []));
        $this->post('/admin/connexion', ['username' => 'fixture-admin', 'password' => 'fixture-only-password-12345'])->assertRedirect('/admin')->assertSessionHas('admin.auth');
        $this->get('/admin/retours')->assertOk();
        config(['admin.password_hash' => Hash::make('rotated-fixture-only-password')]);
        $this->get('/admin/retours')->assertRedirect('/admin/connexion');
        $state = $this->authenticated();
        $state['admin.auth']['seen'] -= 1801;
        $this->withSession($state)->get('/admin')->assertRedirect('/admin/connexion');
        $state = $this->authenticated();
        $state['admin.auth']['started'] -= 28801;
        $this->withSession($state)->get('/admin')->assertRedirect('/admin/connexion');
        $this->withSession($this->authenticated())->post('/admin/deconnexion')->assertRedirect('/admin/connexion')->assertSessionMissing('admin.auth');
    }

    public function test_login_attempts_are_limited_and_credentials_are_never_flashed(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/connexion', ['username' => 'fixture-admin', 'password' => 'wrong'])->assertRedirect();
        }
        $this->post('/admin/connexion', ['username' => 'fixture-admin', 'password' => 'wrong'])->assertStatus(429)->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('wrong');
    }

    public function test_metrics_count_confirmed_payments_only_and_dashboard_reads_do_not_mutate_rows(): void
    {
        $this->database();
        $paid = $this->order('paid', 12345);
        $pending = $this->order('pending_payment_details', 999999);
        $this->order('expired', 888888);
        DB::connection('commerce')->table('commerce_inventory')->insert(['slug' => 'fixture-product', 'reference' => 'FIXTURE-100%', 'quantity' => 1000, 'reserved' => 4, 'approved_price_cents' => 12345, 'sale_enabled' => true, 'validated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $before = DB::connection('commerce')->table('commerce_orders')->orderBy('id')->get()->toJson();
        $this->withSession($this->authenticated())->get('/admin')->assertOk()->assertSee('123,45 €')->assertSee('Commandes enregistrées')->assertDontSee('fixture@example.invalid');
        $this->assertSame(12345, (int) app(AdminDashboard::class)->overview()['paid_cents']);
        $this->get('/admin/commandes?status=paid')->assertOk()->assertSee($paid)->assertDontSee($pending);
        $this->get('/admin/commandes/'.$paid)->assertOk()->assertSee('fixture@example.invalid')->assertSee('&lt;script&gt;fixture&lt;/script&gt;', false)->assertDontSee('<script>fixture</script>', false);
        $this->get('/admin/stocks?q=100%25')->assertOk()->assertSee('996')->assertSee('FIXTURE-100%');
        $this->get('/admin/stocks?q=absent')->assertOk()->assertSee('Aucun produit');
        $this->assertSame($before, DB::connection('commerce')->table('commerce_orders')->orderBy('id')->get()->toJson());
        $this->assertSame(4, DB::connection('commerce')->table('commerce_inventory')->value('reserved'));
    }

    public function test_return_details_are_private_and_missing_storage_or_database_is_not_faked(): void
    {
        $record = app(ReturnRequests::class)->save(['name' => 'Fixture return customer', 'contract' => 'FIXTURE', 'notes' => '<img src=x onerror=alert(1)>']);
        $this->withSession($this->authenticated())->get('/admin/retours')->assertOk()->assertSee($record['id'])->assertDontSee('Fixture return customer');
        $this->get('/admin/retours/'.$record['id'])->assertOk()->assertSee('Fixture return customer')->assertDontSee('<img src=x', false);
        $this->get('/admin/retours/'.Str::uuid())->assertNotFound()->assertDontSee('Exception');
        $this->database(); // empty schema is a genuine zero, not a connection error
        $this->get('/admin')->assertOk()->assertSee('0,00 €');
        DB::connection('commerce')->getSchemaBuilder()->drop('commerce_orders');
        $this->get('/admin')->assertOk()->assertSee('momentanément indisponibles')->assertDontSee('0,00 €');
        $this->get('/admin/commandes')->assertStatus(503)->assertDontSee('SQLSTATE');
    }

    public function test_local_credential_creation_preserves_environment_and_refuses_silent_overwrite(): void
    {
        File::ensureDirectoryExists($this->temporary, 0700);
        File::put($this->temporary.'/.env', "APP_ENV=local\nPROTECTED_FIXTURE=value\n");
        $original = $this->app->environmentPath();
        $this->app->useEnvironmentPath($this->temporary);
        try {
            $this->artisan('homya:admin-create --generate')->assertSuccessful();
            $env = File::get($this->temporary.'/.env');
            $this->assertStringContainsString('PROTECTED_FIXTURE=value', $env);
            $file = collect(File::files($this->temporary.'/app/private/admin'))->first(fn ($file) => str_starts_with($file->getFilename(), 'access-'));
            $this->assertSame(0600, fileperms($file->getPathname()) & 0777);
            preg_match('/Mot de passe : (.+)/', File::get($file->getPathname()), $password);
            preg_match("/HOMYA_ADMIN_PASSWORD_HASH='([^']+)'/", $env, $hash);
            $this->assertTrue(Hash::check($password[1], $hash[1]));
            $this->assertStringNotContainsString($password[1], $env);
            $this->artisan('homya:admin-create --generate')->assertFailed();
            $this->assertSame($env, File::get($this->temporary.'/.env'));
        } finally {
            $this->app->useEnvironmentPath($original);
        }
    }
}
