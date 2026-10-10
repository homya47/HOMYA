<?php

namespace Tests\Feature;

use App\Services\Admin\Activity;
use App\Services\Admin\Deliveries;
use App\Services\Admin\Integrations;
use App\Services\Admin\Messages;
use App\Services\Admin\PrivateSettings;
use App\Services\Admin\ProductManager;
use App\Services\AdminAccess;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AdminManagementTest extends TestCase
{
    private string $temporary;

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->storage = storage_path();
        $this->temporary = sys_get_temp_dir().'/homya-management-test-'.Str::uuid();
        File::ensureDirectoryExists($this->temporary, 0700);
        $this->app->useStoragePath($this->temporary);
        config(['dashboard.enabled' => true, 'admin.username' => 'fixture', 'admin.password_hash' => Hash::make('fixture-private-password'), 'database.connections.commerce' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::purge('commerce');
        (require database_path('migrations/commerce/2026_10_09_000001_create_commerce_tables.php'))->up();
        (require database_path('migrations/commerce/2026_10_09_000002_create_admin_tables.php'))->up();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->storage);
        DB::purge('commerce');
        File::deleteDirectory($this->temporary);
        parent::tearDown();
    }

    private function auth(): array
    {
        return ['admin.auth' => ['fingerprint' => app(AdminAccess::class)->fingerprint(), 'started' => now()->timestamp, 'seen' => now()->timestamp]];
    }

    private function order(string $status): string
    {
        $id = (string) Str::uuid();
        DB::connection('commerce')->table('commerce_orders')->insert(['id' => $id, 'owner_hash' => str_repeat('a', 64), 'checkout_key' => Str::uuid(), 'request_hash' => str_repeat('b', 64), 'locale' => 'fr', 'country' => 'FR', 'currency' => 'EUR', 'subtotal_cents' => 12345, 'shipping_cents' => 0, 'total_cents' => 12345, 'customer_encrypted' => Crypt::encryptString('{"name":"Fixture"}'), 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    private function productData(int $revision = 0): array
    {
        return ['reference' => 'FIXTURE-UNIQUE', 'revision' => $revision, 'price' => '123.45', 'quantity' => 1000, 'published' => false, 'brand' => 'Fixture', 'category' => 'Test', 'universe' => 'other', 'condition' => 'neuf', 'languages' => ['fr' => ['name' => 'Fixture produit', 'description' => 'Description <script>fixture</script>', 'category' => 'Test']]];
    }

    public function test_new_routes_are_protected_and_module_can_remain_disabled(): void
    {
        foreach (['/admin/produits/nouveau', '/admin/messages', '/admin/activite', '/admin/parametres', '/admin/integrations', '/admin/notifications'] as $url) {
            $this->get($url)->assertRedirect('/admin/connexion')->assertHeader('Cache-Control', 'no-store, private');
        }
        config(['dashboard.enabled' => false]);
        $this->withSession($this->auth())->get('/admin/messages')->assertStatus(503)->assertDontSee('SQLSTATE');
    }

    public function test_client_admin_conversation_is_private_encrypted_and_replay_safe(): void
    {
        $this->get('/fr/assistance')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $nonce = session('support.nonce');
        $this->post('/fr/assistance', ['name' => 'Client fixture', 'email' => 'client@example.invalid', 'body' => 'Question <script>fixture</script>', 'nonce' => $nonce])->assertRedirect();
        $thread = DB::connection('commerce')->table('commerce_admin_threads')->first();
        $this->assertStringNotContainsString('Client fixture', $thread->customer_encrypted);
        $this->assertStringNotContainsString('Question', DB::connection('commerce')->table('commerce_admin_messages')->value('body_encrypted'));
        $this->post('/fr/assistance', ['name' => 'Client fixture', 'body' => 'Question', 'nonce' => $nonce])->assertRedirect();
        $this->assertSame(1, DB::connection('commerce')->table('commerce_admin_threads')->count());
        $page = $this->get('/fr/assistance/'.$thread->id)->assertOk()->assertSee('&lt;script&gt;fixture&lt;/script&gt;', false)->assertDontSee('<script>fixture</script>', false);
        $code = Crypt::decryptString(session('support.code.'.$thread->id));
        $this->assertTrue(app(Messages::class)->authorize($thread->id, $code));
        $this->assertFalse(app(Messages::class)->authorize($thread->id, 'wrong'));
        $this->withSession($this->auth())->get('/admin/messages/'.$thread->id)->assertOk()->assertSee('Client fixture');
        $replyNonce = session('admin.reply_nonce.'.$thread->id);
        $data = ['body' => 'Réponse fixture', 'nonce' => $replyNonce];
        $this->post('/admin/messages/'.$thread->id, $data)->assertRedirect();
        $this->post('/admin/messages/'.$thread->id, $data)->assertRedirect();
        $this->assertSame(2, DB::connection('commerce')->table('commerce_admin_messages')->count());
        $this->assertSame(['client', 'admin'], app(Messages::class)->thread($thread->id)['messages']->pluck('author')->all());
        $this->get('/fr/assistance/'.$thread->id)->assertOk()->assertSee('Réponse fixture');
        $this->flushSession();
        $this->get('/fr/assistance/'.$thread->id)->assertNotFound();
        $this->post('/fr/assistance/reprendre', ['id' => $thread->id, 'code' => $code])->assertRedirect('/fr/assistance/'.$thread->id);
        $this->get('/fr/assistance/'.$thread->id)->assertOk();
    }

    public function test_validation_does_not_flash_messages_codes_or_secrets_and_bad_nonce_cannot_reply(): void
    {
        $ticket = app(Messages::class)->create(['name' => 'Fixture', 'email' => ''], 'Fixture body', (string) Str::uuid());
        $this->post('/fr/assistance/reprendre', ['id' => 'bad', 'code' => 'PRIVATE_FIXTURE_CODE'])->assertSessionHasErrors()->assertSessionMissing('_old_input.code');
        $this->withSession($this->auth())->post('/admin/messages/'.$ticket['id'], ['body' => 'PRIVATE_FIXTURE_BODY', 'nonce' => (string) Str::uuid()])->assertStatus(419);
        $this->post('/admin/parametres', ['current_password' => 'wrong', 'secret' => 'PRIVATE_FIXTURE_SECRET'])->assertSessionHasErrors()->assertSessionMissing('_old_input.secret')->assertSessionMissing('_old_input.current_password');
        $this->assertSame(1, DB::connection('commerce')->table('commerce_admin_messages')->count());
    }

    public function test_marking_read_returns_to_the_screen_even_after_a_notification_request(): void
    {
        $ticket = app(Messages::class)->create(['name' => 'Fixture', 'email' => ''], 'Question', (string) Str::uuid());
        $this->withSession($this->auth() + ['_previous' => ['url' => 'http://localhost/admin/notifications']]);
        $this->post('/admin/messages/'.$ticket['id'].'/lire')->assertRedirect('/admin/messages/'.$ticket['id']);
        $this->assertFalse((bool) DB::connection('commerce')->table('commerce_admin_threads')->where('id', $ticket['id'])->value('unread'));
        $ids = DB::connection('commerce')->table('commerce_admin_events')->pluck('id')->all();
        $this->withSession(['_previous' => ['url' => 'http://localhost/admin/notifications']]);
        $this->post('/admin/notifications/lire', ['ids' => $ids])->assertRedirect('/admin/activite');
        $this->assertSame(0, DB::connection('commerce')->table('commerce_admin_events')->whereNull('read_at')->count());
    }

    public function test_email_is_explicit_and_transport_success_is_not_delivery_and_never_replayed(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.invalid', 'mail.from.address' => 'homya@example.invalid', 'contact.email' => 'homya@example.invalid']);
        $ticket = app(Messages::class)->create(['name' => 'Fixture', 'email' => 'client@example.invalid'], 'Question', (string) Str::uuid());
        $nonce = (string) Str::uuid();
        $this->assertTrue(app(Messages::class)->reply($ticket['id'], 'Answer', $nonce, true, true));
        $this->assertFalse(app(Messages::class)->reply($ticket['id'], 'Answer', $nonce, true, true));
        $this->assertSame('submitted', DB::connection('commerce')->table('commerce_admin_messages')->where('nonce', $nonce)->value('email_status'));
        config(['mail.default' => 'log']);
        $other = (string) Str::uuid();
        app(Messages::class)->reply($ticket['id'], 'Answer', $other, true, true);
        $this->assertSame('failed', DB::connection('commerce')->table('commerce_admin_messages')->where('nonce', $other)->value('email_status'));
    }

    public function test_product_editor_is_atomic_guards_revision_and_reserved_stock_and_reference(): void
    {
        $manager = app(ProductManager::class);
        $slug = $manager->save(null, $this->productData());
        $row = DB::connection('commerce')->table('commerce_inventory')->where('slug', $slug)->first();
        $this->assertSame(12345, $row->approved_price_cents);
        $this->assertFalse((bool) $row->sale_enabled);
        DB::connection('commerce')->table('commerce_inventory')->where('slug', $slug)->update(['reserved' => 5]);
        $changed = $this->productData(1);
        $changed['price'] = '234.56';
        $changed['reference'] = 'ATTEMPTED-RENAME';
        $changed['quantity'] = 12;
        $manager->save($slug, $changed);
        $row = DB::connection('commerce')->table('commerce_inventory')->where('slug', $slug)->first();
        $this->assertSame(23456, $row->approved_price_cents);
        $this->assertSame('FIXTURE-UNIQUE', $row->reference);
        $this->assertSame(5, $row->reserved);
        foreach ([$changed, $this->productData(2) + []] as $index => $invalid) {
            if ($index === 1) {
                $invalid['quantity'] = 4;
            }
            try {
                $manager->save($slug, $invalid);
                $this->fail('Invalid edit accepted');
            } catch (ValidationException) {
            }
        }
        $this->assertSame(2, DB::connection('commerce')->table('commerce_admin_products')->where('slug', $slug)->value('revision'));
        $this->assertSame(23456, DB::connection('commerce')->table('commerce_inventory')->where('slug', $slug)->value('approved_price_cents'));
        $publish = $this->productData(2);
        $publish['published'] = true;
        try {
            $manager->save($slug, $publish);
            $this->fail('Published without authentic photo');
        } catch (ValidationException) {
        }
        $this->withSession($this->auth())->get('/admin/produits/'.$slug)->assertOk()->assertSee('234.56')->assertDontSee('<script>fixture</script>', false);
    }

    public function test_invalid_product_price_and_executable_upload_are_refused(): void
    {
        $data = $this->productData();
        $data['price'] = '0';
        $this->withSession($this->auth())->post('/admin/produits/nouveau', $data)->assertSessionHasErrors('price');
        $data['price'] = '12.00';
        $data['image'] = UploadedFile::fake()->createWithContent('fixture.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>fixture</script></svg>');
        $this->post('/admin/produits/nouveau', $data)->assertSessionHasErrors('image');
        $this->assertSame(0, DB::connection('commerce')->table('commerce_inventory')->count());
    }

    public function test_delivery_requires_payment_real_event_and_sequential_revision_and_safe_url(): void
    {
        $paid = $this->order('paid');
        $pending = $this->order('pending_payment_details');
        $this->withSession($this->auth());
        $this->post('/admin/commandes/'.$pending.'/livraison', ['revision' => 0, 'status' => 'processing'])->assertSessionHasErrors('status');
        $this->post('/admin/commandes/'.$paid.'/livraison', ['revision' => 0, 'status' => 'shipped', 'carrier' => 'Fixture', 'tracking_number' => 'TRACK', 'confirmed' => 1])->assertSessionHasErrors('status');
        $this->post('/admin/commandes/'.$paid.'/livraison', ['revision' => 0, 'status' => 'processing'])->assertRedirect();
        $this->post('/admin/commandes/'.$paid.'/livraison', ['revision' => 1, 'status' => 'shipped', 'carrier' => 'Fixture', 'tracking_number' => 'TRACK', 'tracking_url' => 'javascript:fixture', 'confirmed' => 1])->assertSessionHasErrors('tracking_url');
        $data = ['revision' => 1, 'status' => 'shipped', 'carrier' => 'Fixture', 'tracking_number' => 'TRACK', 'tracking_url' => 'https://carrier.example.invalid/track', 'confirmed' => 1];
        $this->post('/admin/commandes/'.$paid.'/livraison', $data)->assertRedirect();
        $this->post('/admin/commandes/'.$paid.'/livraison', $data)->assertSessionHasErrors('revision');
        $data['revision'] = 2;
        $data['status'] = 'delivered';
        unset($data['confirmed']);
        $this->post('/admin/commandes/'.$paid.'/livraison', $data)->assertSessionHasErrors('confirmed');
        $data['confirmed'] = 1;
        $this->post('/admin/commandes/'.$paid.'/livraison', $data)->assertRedirect();
        $this->assertSame('delivered', app(Deliveries::class)->get($paid)->status);
        $this->assertSame('paid', DB::connection('commerce')->table('commerce_orders')->where('id', $paid)->value('status'));
    }

    public function test_cancellation_releases_only_owned_reservation_and_cannot_refund_paid_order(): void
    {
        $pending = $this->order('pending_payment_details');
        $paid = $this->order('paid');
        DB::connection('commerce')->table('commerce_inventory')->insert(['slug' => 'fixture', 'reference' => 'FIX', 'quantity' => 1000, 'reserved' => 7, 'approved_price_cents' => 12345, 'sale_enabled' => true, 'validated_at' => now()]);
        DB::connection('commerce')->table('commerce_order_items')->insert(['order_id' => $pending, 'slug' => 'fixture', 'line_key' => 'fixture', 'reference' => 'FIX', 'name' => 'Fixture', 'quantity' => 2, 'unit_price_cents' => 12345, 'line_cents' => 24690]);
        app(Deliveries::class)->cancel($pending);
        app(Deliveries::class)->cancel($pending);
        $this->assertSame(5, DB::connection('commerce')->table('commerce_inventory')->value('reserved'));
        $this->assertSame(1000, DB::connection('commerce')->table('commerce_inventory')->value('quantity'));
        $this->assertSame('cancelled', DB::connection('commerce')->table('commerce_orders')->where('id', $pending)->value('status'));
        try {
            app(Deliveries::class)->cancel($paid);
            $this->fail('Paid order cancelled');
        } catch (ValidationException) {
        }
        $this->assertSame(1, DB::connection('commerce')->table('commerce_admin_events')->where('type', 'order_cancelled')->count());
    }

    public function test_private_settings_whitelist_version_and_encrypted_backup_preserve_protected_keys(): void
    {
        $path = $this->app->environmentPath();
        $this->app->useEnvironmentPath($this->temporary);
        File::put($this->temporary.'/.env', "APP_KEY=PROTECTED_FIXTURE\nDB_PASSWORD=PRIVATE_FIXTURE\n");
        try {
            $settings = app(PrivateSettings::class);
            $version = $settings->version();
            $settings->save(['HOMYA_CONTACT_PHONE' => '+33123456789', 'STRIPE_SECRET_KEY' => 'sk_test_fixtureonly'], $version);
            $actual = File::get($this->temporary.'/.env');
            $this->assertStringContainsString('DB_PASSWORD=PRIVATE_FIXTURE', $actual);
            $this->assertSame(0600, fileperms($this->temporary.'/.env') & 0777);
            try {
                $settings->save(['HOMYA_CONTACT_PHONE' => '+33999999999'], $version);
                $this->fail('Stale configuration accepted');
            } catch (ValidationException) {
            }
            try {
                $settings->save(['APP_KEY' => 'changed'], $settings->version());
                $this->fail('Protected key edited');
            } catch (\RuntimeException) {
            }
            $backup = collect(File::files(storage_path('app/private/admin')))->first();
            $this->assertStringNotContainsString('PRIVATE_FIXTURE', File::get($backup));
            $this->assertStringContainsString('PRIVATE_FIXTURE', Crypt::decryptString(File::get($backup)));
        } finally {
            $this->app->useEnvironmentPath($path);
        }
    }

    public function test_events_are_deduplicated_and_traffic_is_daily_aggregate_not_visitor_identity(): void
    {
        $activity = app(Activity::class);
        $activity->record('payment_confirmed', 'fixture-order');
        $activity->record('payment_confirmed', 'fixture-order');
        $activity->aggregate('page_view', 'home|fr');
        $activity->aggregate('page_view', 'home|fr');
        $rows = $activity->traffic()['rows'];
        $this->assertSame(1, $rows->count());
        $this->assertSame(2, (int) $rows[0]->total);
        $this->assertSame(1, $activity->counts()['notifications']);
        $this->withSession($this->auth())->get('/admin/notifications')->assertOk()->assertJson(['messages' => 0, 'notifications' => 1]);
        $this->get('/admin/activite')->assertOk()->assertDontSee('192.0.2.');
    }

    public function test_google_and_meta_reports_use_read_endpoints_and_never_relabel_old_account_results(): void
    {
        config(['dashboard.google.client_id' => 'fixture-id', 'dashboard.google.client_secret' => 'fixture-secret', 'dashboard.google.refresh_token' => 'fixture-refresh', 'dashboard.google.ga4' => '123', 'dashboard.google.search_console' => 'sc-domain:example.invalid', 'dashboard.google.ads' => '456', 'dashboard.google.developer_token' => 'fixture-developer', 'dashboard.google.merchant' => '789', 'dashboard.meta.account' => 'act_12345', 'dashboard.meta.token' => 'fixture-meta-token', 'dashboard.meta.version' => 'v25.0']);
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['access_token' => 'fixture-access']), 'analyticsdata.googleapis.com/*' => Http::response(['rows' => [['dimensionValues' => [['value' => '20261008'], ['value' => 'Organic Search']], 'metricValues' => [['value' => '3'], ['value' => '2'], ['value' => '1'], ['value' => '123.45']]]]]), 'www.googleapis.com/*' => Http::response(['rows' => [['keys' => ['2026-10-08'], 'clicks' => 2, 'impressions' => 3, 'ctr' => 0.66, 'position' => 2]]]), 'googleads.googleapis.com/*' => Http::response(['results' => [['customer' => ['currencyCode' => 'EUR'], 'segments' => ['date' => '2026-10-08'], 'metrics' => ['clicks' => 2, 'costMicros' => '1000000']]]]), 'merchantapi.googleapis.com/*' => Http::response(['products' => [['offerId' => 'fixture', 'productAttributes' => ['title' => 'Fixture'], 'productStatus' => ['itemLevelIssues' => [['code' => 'fixture_issue']]]]]]), 'graph.facebook.com/*' => Http::response(['data' => [['impressions' => '12', 'clicks' => '2', 'spend' => '1.23']]])]);
        $integrations = app(Integrations::class);
        foreach (array_keys(Integrations::SERVICES) as $service) {
            $integrations->synchronize($service);
        }
        $this->assertCount(5, $integrations->reports());
        $this->assertSame('Fixture', $integrations->reports()['merchant']['rows'][0]['titre']);
        $this->assertSame('fixture_issue', $integrations->reports()['merchant']['rows'][0]['problemes']);
        config(['dashboard.google.ga4' => '999']);
        $this->assertArrayNotHasKey('ga4', $integrations->reports());
        Http::assertSent(fn ($request) => str_contains($request->url(), '/v24/customers/456/googleAds:search') && ! str_contains($request->body(), 'mutate'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'graph.facebook.com/v25.0/act_12345/insights') && $request->hasHeader('Authorization', 'Bearer fixture-meta-token'));
        Http::swap(new Factory);
        Http::fake(['*' => Http::response(['error' => 'PRIVATE_REMOTE_ERROR'], 403)]);
        $this->withSession($this->auth())->post('/admin/integrations/ga4/synchroniser')->assertSessionHasErrors('integration')->assertDontSee('PRIVATE_REMOTE_ERROR');
        $this->get('/admin/integrations')->assertOk()->assertDontSee('fixture-secret')->assertDontSee('fixture-refresh')->assertDontSee('fixture-meta-token');
    }

    public function test_authentic_image_publication_and_language_projection_leave_archive_untouched(): void
    {
        $public = public_path();
        $this->app->usePublicPath($this->temporary.'/public');
        File::ensureDirectoryExists(public_path(), 0755);
        try {
            $archiveHash = hash_file('sha256', $this->storage.'/app/catalogue/index.json');
            $data = $this->productData();
            $data['published'] = true;
            $upload = UploadedFile::fake()->image('fixture.jpg', 600, 600);
            $slug = app(ProductManager::class)->save(null, $data, $upload, [UploadedFile::fake()->image('fixture-second.png', 600, 600)]);
            $items = app(ProductManager::class)->custom();
            $this->assertCount(1, $items);
            $this->assertSame($slug, $items[0]['slug']);
            $this->assertSame(12345, $items[0]['price_cents']);
            $path = public_path($items[0]['image']['local_url']);
            $this->assertFileExists($path);
            $this->assertSame(IMAGETYPE_WEBP, getimagesize($path)[2]);
            $this->assertCount(2, $items[0]['images']);
            app()->setLocale('en');
            $this->assertSame([], app(ProductManager::class)->custom());
            app()->setLocale('fr');
            $this->assertSame($archiveHash, hash_file('sha256', $this->storage.'/app/catalogue/index.json'));
            $rejected = $this->productData(0);
            $rejected['published'] = true;
            try {
                app(ProductManager::class)->save($slug, $rejected, $upload);
                $this->fail('Stale edit with upload accepted');
            } catch (ValidationException) {
            }
            $this->assertCount(2, File::files(public_path('media/admin-products')));
            $removal = $this->productData(1);
            $removal['published'] = true;
            $removal['remove_images'] = [1];
            app(ProductManager::class)->save($slug, $removal);
            $this->assertCount(1, app(ProductManager::class)->custom()[0]['images']);
            $this->assertCount(2, File::files(public_path('media/admin-products')), 'Existing photos are retained on disk');
        } finally {
            $this->app->usePublicPath($public);
        }
    }

    public function test_google_oauth_state_expiry_one_use_and_private_token_storage(): void
    {
        $original = $this->app->environmentPath();
        $this->app->useEnvironmentPath($this->temporary);
        File::put($this->temporary.'/.env', "APP_KEY=PROTECTED_FIXTURE\n");
        config(['dashboard.google.client_id' => 'fixture-client', 'dashboard.google.client_secret' => 'fixture-secret']);
        try {
            $response = $this->withSession($this->auth())->get('/admin/integrations/google/autoriser')->assertRedirect();
            $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $response->headers->get('Location'));
            $pending = session('admin.google_oauth');
            $this->get('/admin/integrations/google/retour?state=wrong&code=fixture-code')->assertSessionHasErrors('integration');
            Http::assertNothingSent();
            $this->get('/admin/integrations/google/autoriser');
            $pending = session('admin.google_oauth');
            Http::fake(['oauth2.googleapis.com/*' => Http::response(['refresh_token' => 'FIXTURE_PRIVATE_REFRESH'])]);
            $this->get('/admin/integrations/google/retour?'.http_build_query(['state' => $pending['state'], 'code' => 'fixture-code']))->assertRedirect('/admin/integrations')->assertSessionMissing('admin.google_oauth');
            $this->assertStringContainsString('FIXTURE_PRIVATE_REFRESH', File::get($this->temporary.'/.env'));
            $this->get('/admin/integrations')->assertOk()->assertDontSee('FIXTURE_PRIVATE_REFRESH');
            $this->get('/admin/integrations/google/retour?'.http_build_query(['state' => $pending['state'], 'code' => 'fixture-code']))->assertSessionHasErrors('integration');
            Http::assertSentCount(1);
            $this->get('/admin/integrations/google/autoriser');
            $pending = session('admin.google_oauth');
            $pending['created'] -= 301;
            $this->withSession(['admin.google_oauth' => $pending])->get('/admin/integrations/google/retour?'.http_build_query(['state' => $pending['state'], 'code' => 'fixture-code']))->assertSessionHasErrors('integration');
            Http::assertSentCount(1);
        } finally {
            $this->app->useEnvironmentPath($original);
        }
    }

    public function test_sensitive_settings_require_current_password_and_invalid_rib_never_changes_environment(): void
    {
        $original = $this->app->environmentPath();
        $this->app->useEnvironmentPath($this->temporary);
        File::put($this->temporary.'/.env', "PROTECTED_FIXTURE=before\n");
        try {
            $data = ['version' => app(PrivateSettings::class)->version(), 'mode' => 'test', 'email' => 'homya@example.invalid', 'current_password' => 'wrong'];
            $this->withSession($this->auth())->post('/admin/parametres', $data)->assertSessionHasErrors('current_password');
            $data['current_password'] = 'fixture-private-password';
            $data['bank_enabled'] = true;
            $data['iban'] = 'FR0000000000000000000000000';
            $data['beneficiary'] = 'Fixture';
            $data['bank_name'] = 'Fixture Bank';
            $data['bic'] = 'FIXTFRPP';
            $this->post('/admin/parametres', $data)->assertSessionHasErrors('iban');
            $this->assertSame("PROTECTED_FIXTURE=before\n", File::get($this->temporary.'/.env'));
            $this->get('/admin/parametres')->assertOk()->assertSee('Vendeur HOMYA')->assertDontSee('fixture-private-password');
        } finally {
            $this->app->useEnvironmentPath($original);
        }
    }

    public function test_verified_bank_receipt_is_exact_and_payment_event_never_double_counts(): void
    {
        $id = $this->order('pending_bank_transfer');
        $db = DB::connection('commerce');
        $db->table('commerce_orders')->where('id', $id)->update(['customer_encrypted' => Crypt::encryptString(json_encode(['name' => 'Fixture', 'bank_transfer' => ['details' => ['beneficiary' => 'Fixture'], 'deadline' => now()->addHours(72)->toIso8601String()]]))]);
        $db->table('commerce_inventory')->insert(['slug' => 'fixture-bank', 'reference' => 'FIX-BANK', 'quantity' => 1000, 'reserved' => 4, 'approved_price_cents' => 12345, 'sale_enabled' => true, 'validated_at' => now()]);
        $db->table('commerce_order_items')->insert(['order_id' => $id, 'slug' => 'fixture-bank', 'line_key' => 'fixture-bank', 'reference' => 'FIX-BANK', 'name' => 'Fixture', 'quantity' => 1, 'unit_price_cents' => 12345, 'line_cents' => 12345]);
        $this->withSession($this->auth());
        $bad = ['amount' => '12.34', 'reference' => 'FIXTURE-BANK-CREDIT', 'current_password' => 'fixture-private-password', 'confirmed' => 1];
        $this->post('/admin/commandes/'.$id.'/reception', $bad)->assertStatus(503);
        $this->assertSame('pending_bank_transfer', $db->table('commerce_orders')->where('id', $id)->value('status'));
        $this->assertSame(0, $db->table('commerce_admin_events')->where('type', 'payment_confirmed')->count());
        $bad['amount'] = '123.45';
        $this->post('/admin/commandes/'.$id.'/reception', $bad)->assertRedirect();
        $this->post('/admin/commandes/'.$id.'/reception', $bad)->assertRedirect();
        $this->assertSame('paid', $db->table('commerce_orders')->where('id', $id)->value('status'));
        $this->assertSame(999, $db->table('commerce_inventory')->value('quantity'));
        $this->assertSame(3, $db->table('commerce_inventory')->value('reserved'));
        $this->assertSame(1, $db->table('commerce_admin_events')->where('type', 'payment_confirmed')->count());
        $this->get('/admin/livraisons')->assertOk()->assertSee($id)->assertSee('À préparer');
    }
}
