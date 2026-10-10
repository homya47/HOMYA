<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use App\Services\Commerce\BankTransfer;
use App\Services\Commerce\Cart;
use App\Services\Commerce\CheckoutReadiness;
use App\Services\Commerce\InitialInventory;
use App\Services\Commerce\OrderPdf;
use App\Services\Commerce\OrderStore;
use App\Services\Commerce\OrderTerms;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class CommerceTest extends TestCase
{
    private array $products;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['commerce.enabled' => false, 'commerce.legal_ready' => false,
            'app.url' => 'http://127.0.0.1:8000']);
        $this->products = [
            'test-unit' => ['slug' => 'test-unit', 'reference' => 'TEST-UNIT', 'name' => 'Article de test', 'price_cents' => 12345,
                'currency' => 'EUR', 'has_variants' => false, 'variants' => [], 'image' => null, 'issues' => []],
            'test-options' => ['slug' => 'test-options', 'reference' => 'TEST-OPTIONS', 'name' => 'Article à options de test', 'price_cents' => 127500,
                'currency' => 'EUR', 'has_variants' => true, 'variants' => [['title' => 'Jaune', 'sku' => 'TEST-YELLOW'], ['title' => 'Bleu']], 'image' => null, 'issues' => []],
        ];
        $catalogue = Mockery::mock(Catalogue::class);
        $catalogue->shouldReceive('product')->andReturnUsing(fn ($slug) => $this->products[$slug] ?? null);
        $catalogue->shouldReceive('products')->andReturnUsing(fn () => collect($this->products));
        $catalogue->shouldReceive('sourceUnavailable', 'translationUnavailable')->andReturn(false);
        $this->app->instance(Catalogue::class, $catalogue);
        Http::preventStrayRequests();
    }

    private function database(): OrderStore
    {
        config(['database.connections.commerce' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::purge('commerce');
        (require database_path('migrations/commerce/2026_10_09_000001_create_commerce_tables.php'))->up();
        $inventory = app(InitialInventory::class);
        $orders = app(OrderStore::class);
        $inventory->install($inventory->prepare(app(Catalogue::class)), app(Catalogue::class), $orders);

        return $orders;
    }

    private function enable(): void
    {
        config(['commerce.enabled' => true, 'commerce.legal_ready' => true]);
        $this->bankConfiguration();
    }

    public function test_card_preview_is_localized_disabled_and_collects_no_card_data(): void
    {
        DB::shouldReceive('connection')->never();
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/paiement/carte')->assertOk()
                ->assertDontSee(__('commerce.card_form.unconfigured', [], $locale))
                ->assertSee(__('commerce.card_form.intro', [], $locale))
                ->assertDontSee('data-card-payment', false)
                ->assertHeader('Cache-Control', 'no-store, private');
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame(9, $xpath->query('//fieldset[@disabled]//input[@disabled and not(@name)]')->length);
            $this->assertSame(0, $xpath->query('//fieldset//input[@name]')->length);
        }
    }

    public function test_card_provider_is_removed_and_preview_cannot_accept_or_submit_card_data(): void
    {
        $orders = $this->database();
        $this->enableDeferred();
        $this->post('/fr/commande', $this->checkout(['payment_method' => 'deferred']));
        $order = $orders->connection()->table('commerce_orders')->first();
        $url = '/fr/commande/'.$order->id.'/carte';
        $this->get($url)->assertOk()->assertDontSee(__('commerce.card_form.unconfigured'))->assertSee(__('commerce.card_form.intro'))->assertDontSee('Stripe')->assertDontSee('data-card-payment', false);
        $this->assertFalse(app(CheckoutReadiness::class)->cardReady());
        $this->assertFalse(Route::has('stripe.webhook'));
        $this->assertFalse(Route::has('order.card-session'));
        $this->post('/paiement/stripe/webhook')->assertNotFound();
        $this->post($url.'/session', ['card_number' => 'fixture-only'])->assertNotFound();
        $this->withSession(['commerce.owner' => 'outsider'])->get($url)->assertNotFound();
        $this->assertSame(0, $orders->connection()->table('commerce_payment_events')->count());
    }

    public function test_card_fallback_reuses_owned_order_with_sepa_message_and_rejects_card_data(): void
    {
        $orders = $this->database();
        $this->enableDeferred();
        $this->post('/fr/commande', $this->checkout(['payment_method' => 'deferred']));
        $order = $orders->connection()->table('commerce_orders')->first();
        $url = '/fr/commande/'.$order->id.'/carte/virement';
        $this->postJson($url)->assertServiceUnavailable();
        $this->enable();
        $this->postJson($url, ['card_number' => 'fixture-only'])->assertUnprocessable();
        $this->postJson($url)->assertOk()->assertJsonPath('url', 'http://127.0.0.1:8000/fr/commande/'.$order->id.'/virement');
        $this->postJson($url)->assertOk();
        $updated = $orders->owned($order->id, $order->owner_hash);
        $this->assertSame('pending_bank_transfer', $updated->status);
        $this->assertSame($order->created_at, $updated->created_at);
        $this->assertSame('card_unavailable', app(BankTransfer::class)->snapshot($updated)['source']);
        $this->assertSame(1, $orders->connection()->table('commerce_orders')->count());
        $this->assertSame(2, (int) $orders->connection()->table('commerce_inventory')->sum('reserved'));
        $this->get('/fr/commande/'.$order->id.'/virement')->assertSee(__('commerce.card_form.fallback_notice'))->assertSee('DE36000000000000000000');
        $this->get('/fr/commande/'.$order->id.'/virement')->assertSee(__('commerce.card_form.fallback_notice'));
        $this->withSession(['commerce.owner' => 'outsider'])->postJson($url)->assertNotFound();
    }

    public function test_legacy_card_attempt_never_exposes_rib_or_silently_releases_stock(): void
    {
        $orders = $this->database();
        $this->enableDeferred();
        $this->post('/fr/commande', $this->checkout(['payment_method' => 'deferred']));
        $order = $orders->connection()->table('commerce_orders')->first();
        $customer = json_decode(Crypt::decryptString($order->customer_encrypted), true);
        $customer['card_attempted'] = true;
        $orders->connection()->table('commerce_orders')->where('id', $order->id)->update(['status' => 'pending_payment', 'stripe_session_id' => 'legacy-fixture-only', 'customer_encrypted' => Crypt::encryptString(json_encode($customer))]);
        $this->enable();
        $this->postJson('/fr/commande/'.$order->id.'/carte/virement')->assertOk();
        $this->get('/fr/commande/'.$order->id.'/virement')->assertDontSee('DE36000000000000000000')->assertDontSee(__('commerce.card_form.fallback_notice'))->assertSee(__('commerce.bank_verification'));
        $this->assertSame('pending_payment', $orders->owned($order->id, $order->owner_hash)->status);
        $this->assertSame(2, (int) $orders->connection()->table('commerce_inventory')->sum('reserved'));
        $this->assertNull(app(BankTransfer::class)->snapshot($orders->owned($order->id, $order->owner_hash)));
    }

    public function test_complete_order_is_idempotent_and_only_verified_bank_receipt_settles_stock(): void
    {
        $orders = $this->database();
        $this->enable();
        $data = $this->checkout(['total_cents' => 1]);
        $this->post('/fr/commande', $data)->assertRedirect();
        $this->post('/fr/commande', $data)->assertRedirect();
        $order = $orders->connection()->table('commerce_orders')->first();
        $this->assertSame(1, $orders->connection()->table('commerce_orders')->count());
        $this->assertSame(24690, (int) $order->total_cents);
        $this->assertStringNotContainsString('fixture@example.invalid', $order->customer_encrypted);
        $this->get('/fr/commande/'.$order->id.'?paid=1')->assertSee(__('commerce.status.pending_bank_transfer'));
        $orders->confirmBankReceipt($order->id, 24690, 'fixture_verified_bank_receipt');
        $orders->confirmBankReceipt($order->id, 24690, 'fixture_verified_bank_receipt');
        $this->assertSame(998, (int) $orders->connection()->table('commerce_inventory')->where('slug', 'test-unit')->value('quantity'));
        $this->assertSame(0, (int) $orders->connection()->table('commerce_inventory')->sum('reserved'));
        $this->get('/fr/commande/'.$order->id)->assertSee(__('commerce.status.paid'));
        $this->get('/fr/panier')->assertSee(__('commerce.empty'));
        $this->postJson('/fr/commande/'.$order->id.'/carte/virement')->assertJsonPath('url', 'http://127.0.0.1:8000/fr/commande/'.$order->id);
    }

    private function checkout(array $extra = [], string $locale = 'fr'): array
    {
        $this->post('/'.$locale.'/panier', ['slug' => 'test-unit', 'quantity' => 2])->assertRedirect();
        $html = $this->get('/'.$locale.'/commande')->assertOk()->getContent();
        preg_match('/name="checkout_key" value="([^"]+)"/', $html, $key);
        preg_match('/name="quote_hash" value="([^"]+)"/', $html, $quote);

        return $extra + ['checkout_key' => $key[1], 'quote_hash' => $quote[1], 'email' => 'fixture@example.invalid',
            'name' => 'Client de test', 'address' => 'Adresse fictive de test', 'postal_code' => '75001',
            'city' => 'Ville de test', 'country' => 'FR', 'terms' => 1];
    }

    public function test_basket_add_update_remove_recalculates_price_and_ignores_browser_amount(): void
    {
        DB::shouldReceive('connection')->never();
        $this->get('/fr/panier')->assertOk()->assertSee(__('commerce.empty'));
        $this->post('/fr/panier', ['slug' => 'test-unit', 'quantity' => 2, 'price_cents' => 1, 'total' => 1])->assertRedirect('/fr/panier');
        $this->get('/fr/panier')->assertOk()->assertSee('246,90 €')->assertSee('123,45 €')
            ->assertSee(__('commerce.unit_price'))->assertSee('data-cart-quantity', false)
            ->assertSee('cart-checkout', false)->assertDontSee('TVA 20')->assertDontSee('Code promo')
            ->assertSee(__('commerce.bank_cart_unavailable'))->assertSee('72 heures')
            ->assertDontSee('7 jours ouvrés')->assertDontSee('uniquement le virement')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->patch('/fr/panier/test-unit', ['quantity' => 3])->assertRedirect();
        $this->get('/fr/panier')->assertSee('370,35 €');
        $this->products['test-unit']['price_cents'] = 13000;
        $this->get('/fr/panier')->assertSee('390,00 €')->assertDontSee('370,35 €');
        $this->delete('/fr/panier/test-unit')->assertRedirect();
        $this->get('/fr/panier')->assertSee(__('commerce.empty'));
    }

    public function test_buy_now_keeps_existing_items_and_only_enters_a_configured_checkout(): void
    {
        $orders = $this->database();
        $this->post('/fr/panier', ['slug' => 'test-unit', 'quantity' => 1])->assertRedirect('/fr/panier');
        $this->enable();
        $this->post('/fr/panier', ['slug' => 'test-options', 'variant' => 'v2', 'quantity' => 1, 'intent' => 'checkout'])
            ->assertRedirect('/fr/commande');
        $quote = app(Cart::class)->quote($this->app['session.store']);
        $this->assertCount(2, $quote['lines']);
        $this->assertSame(139845, $quote['total_cents']);
        $this->get('/fr/commande')->assertOk()->assertSee(__('commerce.free_returns'));
        $this->postJson('/fr/panier', ['slug' => 'test-unit', 'quantity' => 1, 'intent' => 'https://example.invalid'])
            ->assertUnprocessable();
        config(['commerce.enabled' => false]);
        $this->post('/fr/panier', ['slug' => 'test-options', 'variant' => 'v1', 'quantity' => 1, 'intent' => 'checkout'])
            ->assertRedirect('/fr/panier');
        $this->get('/fr/panier')->assertSee(__('commerce.sales_unavailable'))->assertSee('Article à options de test');
        $this->assertSame(0, $orders->connection()->table('commerce_orders')->count());
    }

    public function test_stock_checks_cover_all_options_before_cart_mutation_and_explain_later_shortages(): void
    {
        $orders = $this->database();
        config(['commerce.inventory_enabled' => true]);
        $orders->connection()->table('commerce_inventory')->where('slug', 'test-options')->update(['quantity' => 2]);
        foreach ($this->products as $slug => &$product) {
            $product += ['brand' => 'Test', 'description' => '', 'specifications' => [], 'images' => [],
                'category' => ['slug' => $slug, 'name' => 'Test'], 'condition' => null, 'description_hidden' => false];
        }
        unset($product);
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/produit/test-options')->assertOk()
                ->assertSee(__('commerce.in_stock', [], $locale))->assertDontSee('unités disponibles')
                ->assertDontSee('units available')->assertDontSee('Stück verfügbar');
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame((string) config('commerce.max_quantity'), $xpath->evaluate('string(//input[@id="product-quantity"]/@max)'));
            $this->assertDoesNotMatchRegularExpression('/[0-9]/', $xpath->evaluate('string(//p[contains(@class,"stock-indicator")])'));
        }
        $this->post('/fr/panier', ['slug' => 'test-options', 'variant' => 'v1', 'quantity' => 2])->assertRedirect();
        $this->postJson('/fr/panier', ['slug' => 'test-options', 'variant' => 'v2', 'quantity' => 1])
            ->assertUnprocessable()->assertJsonPath('errors.quantity.0', __('commerce.insufficient_stock'));
        $this->assertSame(['test-options~v1' => 2], app(Cart::class)->quantities($this->app['session.store']));
        $this->patch('/fr/panier/test-options~v1', ['quantity' => 1])->assertRedirect();
        $this->post('/fr/panier', ['slug' => 'test-options', 'variant' => 'v2', 'quantity' => 1])->assertRedirect();
        $orders->connection()->table('commerce_inventory')->where('slug', 'test-options')->update(['quantity' => 1]);
        $this->assertFalse(app(Cart::class)->quote($this->app['session.store'])['valid']);
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->get('/'.$locale.'/panier')->assertOk()->assertSee(__('commerce.insufficient_stock', [], $locale))
                ->assertSee(__('commerce.review_cart', [], $locale))->assertDontSee('unités disponibles')
                ->assertDontSee('units available')->assertDontSee('Stück verfügbar');
            $error = $this->postJson('/'.$locale.'/panier', ['slug' => 'test-options', 'variant' => 'v2', 'quantity' => 1])
                ->assertUnprocessable()->assertJsonPath('errors.quantity.0', __('commerce.insufficient_stock', [], $locale));
            $this->assertDoesNotMatchRegularExpression('/[0-9]/', $error->json('errors.quantity.0'));
        }
        $this->assertSame(0, $orders->connection()->table('commerce_orders')->count());
        $this->assertSame(0, $orders->connection()->table('commerce_inventory')->sum('reserved'));
        $orders->connection()->table('commerce_inventory')->where('slug', 'test-options')->update(['quantity' => 0]);
        $this->get('/fr/produit/test-options')->assertOk()->assertSee(__('commerce.out_of_stock', [], 'fr'));
        $this->postJson('/fr/panier', ['slug' => 'test-options', 'variant' => 'v1', 'quantity' => 1])->assertUnprocessable();
    }

    public function test_quantities_unknown_products_and_corrupt_cart_are_rejected(): void
    {
        foreach ([0, -1, 100, 'abc', [1]] as $quantity) {
            $this->postJson('/fr/panier', ['slug' => 'test-unit', 'quantity' => $quantity])->assertUnprocessable();
        }
        $this->postJson('/fr/panier', ['slug' => 'unknown', 'quantity' => 1])->assertUnprocessable();
        $this->withSession(['commerce.cart' => ['../private' => 1]])->getJson('/fr/panier')->assertUnprocessable();
    }

    public function test_checkout_summary_action_is_bound_to_the_required_form_in_every_language(): void
    {
        $orders = $this->database();
        $this->enable();
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->checkout(locale: $locale);
            $response = $this->get('/'.$locale.'/commande')->assertOk()
                ->assertSee(__('commerce.confirm_order_action', [], $locale))
                ->assertSee(__('commerce.free_returns', [], $locale));
            $document = new \DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame(1, $xpath->query('//form[@id="checkout-form" and @method="post"]')->length);
            $this->assertSame(1, $xpath->query('//aside//button[@type="submit" and @form="checkout-form"]')->length);
            foreach (['name', 'email', 'address', 'postal_code', 'city', 'country', 'terms'] as $field) {
                $this->assertSame(1, $xpath->query('//form[@id="checkout-form"]//*[@name="'.$field.'" and @required]')->length);
            }
            $this->assertSame(0, $xpath->query('//form[@id="checkout-form"]//*[@name="phone" or @name="coupon"]')->length);
            $this->postJson('/'.$locale.'/commande', ['terms' => false])->assertUnprocessable();
        }
        $this->assertSame(0, $orders->connection()->table('commerce_orders')->count());
        $this->assertSame(0, $orders->connection()->table('commerce_inventory')->sum('reserved'));
    }

    public function test_invalid_cart_and_checkout_values_have_readable_localized_messages(): void
    {
        $orders = $this->database();
        $this->enable();
        foreach (['fr', 'en', 'de'] as $locale) {
            $quantityMessage = __('validation.max.numeric', ['max' => config('commerce.max_quantity')], $locale);
            $this->postJson('/'.$locale.'/panier', ['slug' => 'test-unit', 'quantity' => 100])
                ->assertUnprocessable()->assertJsonPath('errors.quantity.0', $quantityMessage);
            $this->patchJson('/'.$locale.'/panier/test-unit', ['quantity' => 100])
                ->assertUnprocessable()->assertJsonPath('errors.quantity.0', $quantityMessage);
            $this->postJson('/'.$locale.'/commande', ['email' => 'invalid', 'terms' => false])
                ->assertUnprocessable()->assertJsonPath('errors.email.0', __('validation.email', [], $locale))
                ->assertJsonPath('errors.terms.0', __('validation.accepted', ['attribute' => __('validation.attributes.terms', [], $locale)], $locale));
        }
        $this->assertSame(0, $orders->connection()->table('commerce_orders')->count());
        $this->assertSame(0, $orders->connection()->table('commerce_inventory')->sum('reserved'));
    }

    public function test_unknown_price_or_currency_cannot_be_added_and_removed_product_blocks_checkout(): void
    {
        $this->products['test-unit']['price_cents'] = null;
        $this->postJson('/fr/panier', ['slug' => 'test-unit', 'quantity' => 1])->assertUnprocessable();
        $this->products['test-unit']['price_cents'] = 12345;
        $this->products['test-unit']['currency'] = 'USD';
        $this->postJson('/fr/panier', ['slug' => 'test-unit', 'quantity' => 1])->assertUnprocessable();
        $this->products['test-unit']['currency'] = 'EUR';
        $this->post('/fr/panier', ['slug' => 'test-unit', 'quantity' => 1]);
        unset($this->products['test-unit']);
        $this->get('/fr/panier')->assertOk()->assertSee(__('commerce.unavailable_product'))->assertDontSee('123,45 €');
    }

    public function test_variants_are_explicit_lines_with_common_price_and_shared_inventory(): void
    {
        $orders = $this->database();
        $this->postJson('/fr/panier', ['slug' => 'test-options', 'quantity' => 1])->assertUnprocessable();
        $this->postJson('/fr/panier', ['slug' => 'test-options', 'quantity' => 1, 'variant' => 'v99'])->assertUnprocessable();
        foreach (['v1', 'v2'] as $variant) {
            $this->post('/fr/panier', ['slug' => 'test-options', 'quantity' => 1, 'variant' => $variant])->assertRedirect();
        }
        $this->get('/fr/panier')->assertSee('Jaune')->assertSee('Bleu')->assertSee(Catalogue::money(255000, 'fr'));
        $this->patch('/fr/panier/test-options~v2', ['quantity' => 2])->assertRedirect();
        $quote = app(Cart::class)->quote($this->app['session.store']);
        $orders->connection()->table('commerce_inventory')->where('slug', 'test-options')->update(['quantity' => 2]);
        try {
            $orders->reserve($quote, ['country' => 'FR'], str_repeat('a', 64), (string) Str::uuid(), 'fr');
            $this->fail('Shared inventory should block three units from a two-unit stock.');
        } catch (ValidationException) {
            $this->assertSame(0, $orders->connection()->table('commerce_orders')->count());
            $this->assertSame(0, (int) $orders->connection()->table('commerce_inventory')->where('slug', 'test-options')->value('reserved'));
        }
    }

    public function test_checkout_cannot_collect_customer_data_when_unconfigured(): void
    {
        DB::shouldReceive('connection')->never();
        $this->get('/de/commande')->assertServiceUnavailable()->assertDontSee('name="email"', false);
        $this->post('/fr/commande', ['email' => 'fixture@example.invalid'])->assertServiceUnavailable();
    }

    public function test_price_change_after_review_and_missing_terms_do_not_create_order(): void
    {
        $orders = $this->database();
        $this->enable();
        $data = $this->checkout();
        $this->postJson('/fr/commande', array_replace($data, ['terms' => false]))->assertUnprocessable();
        $this->from('/fr/commande')->post('/fr/commande', array_replace($data, ['postal_code' => 'invalid']))->assertRedirect('/fr/commande');
        $this->get('/fr/commande')->assertOk()->assertSee('value="fixture@example.invalid"', false)->assertSee('aria-describedby="checkout-postal_code-error"', false);
        $this->products['test-unit']['price_cents'] = 13000;
        $this->postJson('/fr/commande', $data)->assertUnprocessable();
        $this->assertSame(0, $orders->connection()->table('commerce_orders')->count());
    }

    public function test_stock_reference_or_approved_price_mismatch_blocks_payment_and_rolls_back(): void
    {
        $orders = $this->database();
        $this->enable();
        $data = $this->checkout();
        foreach ([['quantity' => 1], ['quantity' => 1000, 'reference' => 'WRONG-REF'], ['reference' => 'TEST-UNIT', 'approved_price_cents' => 1]] as $changes) {
            $orders->connection()->table('commerce_inventory')->where('slug', 'test-unit')->update($changes);
            $this->postJson('/fr/commande', $data)->assertUnprocessable();
            $this->assertSame(0, $orders->connection()->table('commerce_orders')->count());
        }
    }

    public function test_initial_inventory_is_complete_consistent_and_never_overwrites_existing_stock(): void
    {
        $orders = $this->database();
        $inventory = app(InitialInventory::class);
        $manifest = $inventory->prepare(app(Catalogue::class));
        $this->assertSame(2, $manifest['summary']['sale_eligible']);
        $this->assertSame(2000, $manifest['summary']['initial_units']);
        $orders->connection()->table('commerce_inventory')->where('slug', 'test-unit')->update(['quantity' => 998]);
        $this->expectException(\RuntimeException::class);
        $inventory->install($manifest, app(Catalogue::class), $orders);
    }

    private function enableDeferred(): void
    {
        config(['commerce.enabled' => true, 'commerce.deferred_enabled' => true, 'commerce.legal_ready' => false]);
    }

    public function test_deferred_orders_open_without_provider_preserve_owner_and_never_claim_payment(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $orders = $this->database();
        $this->enableDeferred();
        foreach (['fr', 'en', 'de'] as $locale) {
            $data = $this->checkout(['payment_method' => 'deferred'], $locale);
            $this->get('/'.$locale.'/panier')->assertSee('/'.$locale.'/commande')->assertDontSee(__('commerce.sales_unavailable', [], $locale));
            $this->get('/'.$locale.'/commande')->assertSee(__('commerce.deferred_notice', [], $locale))
                ->assertSee('value="deferred"', false)->assertDontSee('value="card"', false)->assertDontSee('value="bank_transfer"', false);
            $this->post('/'.$locale.'/commande', $data + ['total_cents' => 1])->assertRedirect();
            $order = $orders->connection()->table('commerce_orders')->latest('created_at')->first();
            $url = '/'.$locale.'/commande/'.$order->id;
            $this->assertSame('pending_payment_details', $order->status);
            $storedCustomer = json_decode(Crypt::decryptString($order->customer_encrypted), true);
            $this->assertSame(app(OrderTerms::class)->hash(), $storedCustomer['terms_hash']);
            $this->get('/'.$locale.'/cgv')->assertOk()->assertDontSee(__('commerce.deferred_legal_notice', [], $locale));
            $this->get('/'.$locale.'/confidentialite')->assertOk()->assertSee(__('commerce.deferred_data_notice', [], $locale));
            $this->get('/'.$locale.'/paiement')->assertOk()->assertSee(__('commerce.deferred_notice', [], $locale));
            $this->get('/'.$locale.'/suivi')->assertOk()->assertSee(__('tracking.intro', [], $locale))->assertSee('name="reference"', false);
            $this->assertNull($order->paid_at);
            $this->assertNull($order->stripe_session_id);
            $this->assertSame(24690, (int) $order->total_cents);
            $this->post('/'.$locale.'/commande', $data)->assertRedirect($url);
            $this->assertSame(1, $orders->connection()->table('commerce_orders')->count());
            $this->get($url)->assertOk()->assertSee(__('commerce.deferred_confirmation', [], $locale))
                ->assertSee(__('commerce.deferred_contact', [], $locale))->assertDontSee(__('commerce.resume_payment', [], $locale));
            $this->get($url.'/document/summary')->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->get($url.'/document/bank')->assertNotFound();
            $this->get($url.'/virement')->assertRedirect($url);
            $this->post($url.'/paiement')->assertServiceUnavailable();
            $this->post($url.'/virement')->assertServiceUnavailable();
            $this->post($url.'/preuve')->assertStatus(409);
            $this->withSession(['commerce.owner' => 'foreign-fixture'])->get($url)->assertNotFound();
            $this->withSession(['commerce.owner' => 'foreign-fixture'])->get($url.'/document/summary')->assertNotFound();
            $this->assertSame(0, $orders->connection()->table('commerce_payment_events')->count());
            $this->assertSame(2, (int) $orders->connection()->table('commerce_inventory')->where('slug', 'test-unit')->value('reserved'));
            $this->assertSame(1000, (int) $orders->connection()->table('commerce_inventory')->where('slug', 'test-unit')->value('quantity'));
            $this->flushSession();
            $orders = $this->database();
        }
    }

    public function test_deferred_expiry_releases_once_and_payment_activation_preserves_deadline(): void
    {
        $orders = $this->database();
        $this->enableDeferred();
        $data = $this->checkout(['payment_method' => 'deferred']);
        $this->post('/fr/commande', $data)->assertRedirect();
        $order = $orders->connection()->table('commerce_orders')->first();
        $deadline = app(BankTransfer::class)->deadline($order);
        $this->travelTo($deadline->copy()->subSecond());
        $this->assertSame(0, $orders->expireBankTransfers());
        $this->travelTo($deadline);
        $this->assertSame(1, $orders->expireBankTransfers());
        $this->assertSame(0, $orders->expireBankTransfers());
        $this->assertSame('expired', $orders->owned($order->id, $order->owner_hash)->status);
        $this->assertSame(0, (int) $orders->connection()->table('commerce_inventory')->sum('reserved'));
        $this->enable();
        $this->bankConfiguration();
        $this->post('/fr/commande/'.$order->id.'/virement')->assertRedirect();
        $this->assertSame('expired', $orders->owned($order->id, $order->owner_hash)->status);
        $this->travelBack();
        $this->get('/fr/commande')->assertOk();
        $this->enableDeferred();
        config(['commerce.bank.enabled' => false]);
        $data = $this->checkout(['payment_method' => 'deferred']);
        $this->post('/fr/commande', $data)->assertRedirect();
        $next = $orders->connection()->table('commerce_orders')->where('status', 'pending_payment_details')->first();
        $this->travelTo(now()->addHours(24));
        $this->enable();
        $this->bankConfiguration();
        $this->post('/fr/commande/'.$next->id.'/virement')->assertRedirect('/fr/commande/'.$next->id.'/virement');
        $updated = $orders->owned($next->id, $next->owner_hash);
        $this->assertSame('pending_bank_transfer', $updated->status);
        $this->assertSame($next->created_at, $updated->created_at);
        $this->assertSame(app(BankTransfer::class)->deadline($next)->toIso8601String(), app(BankTransfer::class)->snapshot($updated)['deadline']);
        $this->assertSame(2, $orders->connection()->table('commerce_orders')->count());
        $this->travelBack();
    }

    public function test_deferred_mode_is_explicit_and_production_still_requires_secure_validated_configuration(): void
    {
        $readiness = app(CheckoutReadiness::class);
        config(['commerce.enabled' => true, 'commerce.legal_ready' => false]);
        $this->assertFalse($readiness->ready());
        config(['commerce.deferred_enabled' => true]);
        $this->assertTrue($readiness->deferredReady());
        $this->assertFalse($readiness->cardReady());
        $this->assertFalse($readiness->bankReady());
        $this->app['env'] = 'production';
        config(['app.url' => 'https://homya.example', 'app.debug' => false]);
        $this->assertFalse($readiness->deferredReady());
        config(['commerce.legal_ready' => true]);
        $this->assertTrue($readiness->deferredReady());
        config(['app.debug' => true]);
        $this->assertFalse($readiness->deferredReady());
        config(['app.debug' => false, 'app.url' => 'http://homya.example']);
        $this->assertFalse($readiness->deferredReady());
        $this->app['env'] = 'testing';
        config(['app.url' => 'https://homya.example']);
        $this->assertFalse($readiness->deferredReady());
    }

    private function bankConfiguration(): void
    {
        // Artificial account consisting of zeroes with a computed IBAN checksum; no real beneficiary.
        config(['commerce.bank' => ['enabled' => true, 'beneficiary' => 'Bénéficiaire fictif de test',
            'bank_name' => 'Banque fictive de test', 'iban' => 'DE36000000000000000000', 'bic' => 'TESTDE00']]);
    }

    public function test_private_documents_follow_order_snapshot_and_block_foreign_and_expired_bank_access(): void
    {
        $orders = $this->database();
        $this->enable();
        $this->bankConfiguration();
        $this->post('/fr/commande', $this->checkout(['payment_method' => 'bank_transfer']))->assertRedirect();
        $order = $orders->connection()->table('commerce_orders')->first();
        config(['commerce.bank.beneficiary' => 'Changed fixture beneficiary']);
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach (['bank', 'summary'] as $kind) {
                $response = $this->get('/'.$locale.'/commande/'.$order->id.'/document/'.$kind)->assertOk()
                    ->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private')
                    ->assertHeader('Referrer-Policy', 'no-referrer');
                $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
                $this->assertStringContainsString($order->id, $response->getContent());
                $this->assertStringNotContainsString('Changed fixture beneficiary', $response->getContent());
                $this->assertStringNotContainsString('/JavaScript', $response->getContent());
                if ($kind === 'bank') {
                    $this->assertStringContainsString('DE36000000000000000000', $response->getContent());
                } else {
                    $this->assertStringContainsString('TEST-UNIT', $response->getContent());
                    $this->assertStringContainsString('fixture@example.invalid', $response->getContent());
                    $this->assertStringContainsString('CM2C', $response->getContent());
                    $this->assertStringContainsString('https://www.cm2c.net/', $response->getContent());
                    $this->assertStringContainsString('declarer-un-litige@cm2c.net', $response->getContent());
                    $this->assertStringContainsString('+33189470014', $response->getContent());
                }
            }
        }
        $longOrder = clone $order;
        $longOrder->total_cents = 246900;
        $longItems = array_fill(0, 20, (object) ['name' => str_repeat('W', 500), 'variant_name' => '', 'reference' => str_repeat('W', 255), 'variant_reference' => '', 'quantity' => 1, 'line_cents' => 12345]);
        $longPdf = app(OrderPdf::class)->render($longOrder, $longItems, 'summary', 'fr');
        preg_match('/\/Count ([0-9]+)/', $longPdf, $pageCount);
        $this->assertGreaterThan(1, (int) $pageCount[1]);
        $customer = json_decode(Crypt::decryptString($order->customer_encrypted), true);
        $customer['name'] = '客户';
        $orders->connection()->table('commerce_orders')->where('id', $order->id)->update(['customer_encrypted' => Crypt::encryptString(json_encode($customer))]);
        $this->from('/fr/commande/'.$order->id.'/virement')->get('/fr/commande/'.$order->id.'/document/summary')
            ->assertRedirect('/fr/commande/'.$order->id.'/virement')->assertSessionHasErrors('document');
        $orders->connection()->table('commerce_orders')->where('id', $order->id)->update(['customer_encrypted' => $order->customer_encrypted]);
        $this->travel(73)->hours();
        $this->get('/fr/commande/'.$order->id.'/document/bank')->assertNotFound();
        $this->get('/fr/commande/'.$order->id.'/document/summary')->assertOk();
        $this->app['session']->forget('commerce.owner');
        foreach (['bank', 'summary'] as $kind) {
            $this->get('/fr/commande/'.$order->id.'/document/'.$kind)->assertNotFound();
        }
    }

    public function test_customer_transfer_proof_is_private_encrypted_bounded_and_never_confirms_payment(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('local');
        $orders = $this->database();
        $this->enable();
        $this->bankConfiguration();
        $this->post('/fr/commande', $this->checkout(['payment_method' => 'bank_transfer']))->assertRedirect();
        $order = $orders->connection()->table('commerce_orders')->first();
        $url = '/fr/commande/'.$order->id.'/preuve';
        $pdf = app(OrderPdf::class)->render($order, $orders->items($order->id), 'summary', 'fr');
        $this->post($url, ['proof' => UploadedFile::fake()->createWithContent('proof.pdf', $pdf)])->assertRedirect();
        $this->post($url, ['proof' => UploadedFile::fake()->createWithContent('same.pdf', $pdf)])->assertRedirect();
        $files = Storage::disk('local')->allFiles();
        $this->assertCount(1, $files);
        $encrypted = Storage::disk('local')->get($files[0]);
        $this->assertStringNotContainsString('%PDF', $encrypted);
        $record = json_decode(Crypt::decryptString($encrypted), true);
        $this->assertSame('quarantined_unverified', $record['status']);
        $this->assertSame($pdf, base64_decode($record['bytes']));
        $this->postJson($url, ['proof' => UploadedFile::fake()->createWithContent('malicious.pdf', '<?php echo 1;')])->assertUnprocessable();
        $this->postJson($url, ['proof' => UploadedFile::fake()->createWithContent('file.php', $pdf)])->assertUnprocessable();
        $this->postJson($url, ['proof' => UploadedFile::fake()->create('large.pdf', 2049, 'application/pdf')])->assertUnprocessable();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a0OYAAAAASUVORK5CYII=');
        $this->post($url, ['proof' => UploadedFile::fake()->createWithContent('proof.png', $png)])->assertRedirect();
        $this->post($url, ['proof' => UploadedFile::fake()->createWithContent('proof.pdf', $pdf."\n% fixture 2")])->assertRedirect();
        $this->postJson($url, ['proof' => UploadedFile::fake()->createWithContent('proof.pdf', $pdf."\n% fixture 3")])->assertUnprocessable();
        $this->assertCount(3, Storage::disk('local')->allFiles());
        $this->assertSame('pending_bank_transfer', $orders->owned($order->id, $order->owner_hash)->status);
        $this->assertNull($orders->owned($order->id, $order->owner_hash)->paid_at);
        $this->assertSame(2, $orders->connection()->table('commerce_inventory')->sum('reserved'));
        $this->assertSame(0, $orders->connection()->table('commerce_payment_events')->count());
        $this->travel(73)->hours();
        $this->post($url, ['proof' => UploadedFile::fake()->createWithContent('proof.pdf', $pdf)])->assertStatus(409);
        $this->app['session']->forget('commerce.owner');
        $this->post($url, ['proof' => UploadedFile::fake()->createWithContent('proof.pdf', $pdf)])->assertNotFound();
    }

    public function test_address_lookup_is_bounded_localized_and_has_a_manual_fallback(): void
    {
        Http::preventStrayRequests();
        $fixture = ['features' => [
            ['properties' => ['name' => '12 rue fictive', 'postcode' => '75001', 'city' => 'Paris', 'coordinates' => 'not returned']],
            ['properties' => ['name' => 'Invalid', 'postcode' => 'unknown', 'city' => 'Test']],
        ]];
        Http::fake(['https://data.geopf.fr/*' => Http::sequence()->push($fixture)->push($fixture)->push($fixture)->push([], 500)]);
        $this->postJson('/fr/commande/adresses', ['address' => '12 rue fictive', 'country' => 'FR'])->assertStatus(503);
        Http::assertNothingSent();
        $this->enable();
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->postJson('/'.$locale.'/commande/adresses', ['address' => '12 rue fictive', 'country' => 'FR'])
                ->assertOk()->assertExactJson(['suggestions' => [['address' => '12 rue fictive', 'postal_code' => '75001', 'city' => 'Paris']]])
                ->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->postJson('/fr/commande/adresses', ['address' => '12 rue fictive', 'country' => 'BE'])->assertUnprocessable();
        $this->postJson('/fr/commande/adresses', ['address' => str_repeat('a', 201), 'country' => 'FR'])->assertUnprocessable();
        $this->postJson('/fr/commande/adresses', ['address' => '12 rue fictive', 'country' => 'FR'])
            ->assertStatus(503)->assertExactJson(['suggestions' => [], 'unavailable' => true]);
    }

    public function test_transfer_proof_rate_limit_is_independent_of_checkout_and_keeps_the_order_unpaid(): void
    {
        Storage::fake('local');
        $orders = $this->database();
        $this->enable();
        $this->bankConfiguration();
        $this->post('/fr/commande', $this->checkout(['payment_method' => 'bank_transfer']))->assertRedirect();
        $order = $orders->connection()->table('commerce_orders')->first();
        $url = '/fr/commande/'.$order->id.'/preuve';
        $pdf = app(OrderPdf::class)->render($order, $orders->items($order->id), 'summary', 'fr');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post($url, ['proof' => UploadedFile::fake()->createWithContent('proof.pdf', $pdf)])->assertRedirect();
        }
        $this->post($url, ['proof' => UploadedFile::fake()->createWithContent('proof.pdf', $pdf)])->assertStatus(429);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertSame('pending_bank_transfer', $orders->owned($order->id, $order->owner_hash)->status);
    }

    public function test_bank_order_redirects_to_private_rib_page_without_card_provider_and_preserves_snapshot(): void
    {
        $orders = $this->database();
        $this->enable();
        $this->bankConfiguration();
        $data = $this->checkout(['payment_method' => 'bank_transfer']);
        $this->get('/fr/commande')->assertOk()->assertSee('bank-transfer-guide', false)
            ->assertSee(__('contact.purchase_help_heading'))->assertDontSee('DE36000000000000000000');
        $this->get('/fr/panier')->assertSee(__('commerce.bank_cart_title'))
            ->assertDontSee(__('commerce.bank_cart_unavailable'))->assertDontSee('DE36000000000000000000')
            ->assertDontSee('Bénéficiaire fictif de test');
        $this->post('/fr/commande', $data)->assertRedirect();
        $order = $orders->connection()->table('commerce_orders')->first();
        $url = '/fr/commande/'.$order->id.'/virement';
        $this->get($url)->assertOk()->assertSee('Bénéficiaire fictif de test')->assertSee('DE36000000000000000000')
            ->assertSee('246,90 €')->assertSee($order->id)->assertSee('72 heures')->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame('pending_bank_transfer', $order->status);
        $this->assertNull($order->paid_at);
        $this->assertSame(2, $orders->connection()->table('commerce_inventory')->where('slug', 'test-unit')->value('reserved'));
        config(['commerce.bank.beneficiary' => 'Nouveau bénéficiaire de test']);
        $this->get($url)->assertSee('Bénéficiaire fictif de test')->assertDontSee('Nouveau bénéficiaire de test');
        $this->post('/fr/commande', $data)->assertRedirect($url);
        $this->assertSame(1, $orders->connection()->table('commerce_orders')->count());
        foreach (['en', 'de'] as $locale) {
            $this->get('/'.$locale.'/commande/'.$order->id.'/virement')->assertOk()->assertSee('DE36000000000000000000');
        }
        $this->app['session']->forget('commerce.owner');
        $this->get($url)->assertNotFound();
    }

    public function test_missing_card_configuration_automatically_uses_bank_but_missing_rib_never_opens_orders(): void
    {
        $orders = $this->database();
        $this->enable();
        $this->bankConfiguration();
        $data = $this->checkout();
        $this->post('/fr/commande', $data)->assertRedirect();
        $order = $orders->connection()->table('commerce_orders')->first();
        $this->assertSame('pending_bank_transfer', $order->status);
        config(['commerce.bank.iban' => 'DE00000000000000000000']);
        $this->get('/fr/commande')->assertServiceUnavailable();
        $this->get('/fr/commande/'.$order->id.'/virement')->assertSee('DE36000000000000000000');
        config(['commerce.bank.iban' => '']);
        $this->post('/fr/commande', $data)->assertServiceUnavailable();
        $this->assertSame(1, $orders->connection()->table('commerce_orders')->count());
    }

    public function test_bank_reservations_expire_at_exactly_72_hours_and_release_once(): void
    {
        $orders = $this->database();
        $this->enable();
        $this->bankConfiguration();
        $data = $this->checkout(['payment_method' => 'bank_transfer']);
        $this->post('/fr/commande', $data)->assertRedirect();
        $order = $orders->connection()->table('commerce_orders')->first();
        $deadline = app(BankTransfer::class)->deadline($order);
        $this->travelTo($deadline->copy()->subSecond());
        $this->assertSame(0, $orders->expireBankTransfers());
        $this->assertSame(2, $orders->connection()->table('commerce_inventory')->where('slug', 'test-unit')->value('reserved'));
        $this->travelTo($deadline);
        $this->assertSame(1, $orders->expireBankTransfers());
        $this->assertSame(0, $orders->expireBankTransfers());
        $this->assertSame('bank_transfer_expired', $orders->owned($order->id, $order->owner_hash)->status);
        $this->assertSame(1000, $orders->connection()->table('commerce_inventory')->where('slug', 'test-unit')->value('quantity'));
        $this->assertSame(0, $orders->connection()->table('commerce_inventory')->where('slug', 'test-unit')->value('reserved'));
        $this->get('/fr/commande/'.$order->id.'/virement')->assertDontSee('DE36000000000000000000')->assertSee(__('commerce.bank_expired'));
        $this->post('/fr/commande/'.$order->id.'/virement')->assertRedirect();
        $this->assertSame('bank_transfer_expired', $orders->owned($order->id, $order->owner_hash)->status);
        $html = $this->get('/fr/commande')->assertOk()->getContent();
        preg_match('/name="checkout_key" value="([^"]+)"/', $html, $freshKey);
        $this->assertNotSame($data['checkout_key'], $freshKey[1]);
        $this->post('/fr/commande', array_replace($data, ['checkout_key' => $freshKey[1]]))->assertRedirect();
        $this->assertSame(2, $orders->connection()->table('commerce_orders')->count());
        $this->travelBack();
    }

    public function test_only_verified_exact_bank_receipt_pays_once_and_cannot_be_reused_for_another_order(): void
    {
        $orders = $this->database();
        $this->enable();
        $this->bankConfiguration();
        $data = $this->checkout(['payment_method' => 'bank_transfer']);
        $this->post('/fr/commande', $data)->assertRedirect();
        $order = $orders->connection()->table('commerce_orders')->first();
        $this->get('/fr/commande/'.$order->id.'/virement?paid=true')->assertSee(__('commerce.bank_recorded'));
        try {
            $orders->confirmBankReceipt($order->id, 1, 'bank_fixture_wrong_amount');
            $this->fail('Mismatched receipt accepted.');
        } catch (\RuntimeException) {
            $this->assertSame('pending_bank_transfer', $orders->owned($order->id, $order->owner_hash)->status);
        }
        $orders->confirmBankReceipt($order->id, 24690, 'bank_fixture_receipt_unique');
        $orders->confirmBankReceipt($order->id, 24690, 'bank_fixture_receipt_unique');
        $this->assertSame('paid', $orders->owned($order->id, $order->owner_hash)->status);
        $this->assertSame(998, $orders->connection()->table('commerce_inventory')->where('slug', 'test-unit')->value('quantity'));
        $this->assertSame(0, $orders->connection()->table('commerce_inventory')->where('slug', 'test-unit')->value('reserved'));
        $this->assertSame(1, $orders->connection()->table('commerce_payment_events')->count());
        $data = $this->checkout(['payment_method' => 'bank_transfer']);
        $this->post('/fr/commande', $data)->assertRedirect();
        $second = $orders->connection()->table('commerce_orders')->where('id', '!=', $order->id)->first();
        try {
            $orders->confirmBankReceipt($second->id, 49380, 'bank_fixture_receipt_unique');
            $this->fail('Receipt reused on another order.');
        } catch (\RuntimeException) {
            $this->assertSame('pending_bank_transfer', $orders->owned($second->id, $second->owner_hash)->status);
        }
        $this->travelTo(app(BankTransfer::class)->deadline($second));
        try {
            $orders->confirmBankReceipt($second->id, 49380, 'bank_fixture_late_receipt');
            $this->fail('Late receipt accepted without reconciliation.');
        } catch (\RuntimeException) {
            $this->assertSame('pending_bank_transfer', $orders->owned($second->id, $second->owner_hash)->status);
        }
        $orders->expireBankTransfers();
        $this->assertSame('paid', $orders->owned($order->id, $order->owner_hash)->status);
        $this->travelBack();
    }

    public function test_private_bank_receipt_command_rejects_public_inputs_and_settles_once(): void
    {
        $orders = $this->database();
        $this->enable();
        $this->bankConfiguration();
        $data = $this->checkout(['payment_method' => 'bank_transfer']);
        $this->post('/fr/commande', $data)->assertRedirect();
        $order = $orders->connection()->table('commerce_orders')->first();
        $directory = storage_path('app/private/commerce/receipts');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $path = $directory.'/qa-'.Str::uuid().'.json';
        $receipt = ['order_id' => $order->id, 'amount_cents' => 24690, 'currency' => 'EUR',
            'bank_reference' => 'qa-private-'.Str::uuid(), 'merchant_verified' => true];
        try {
            file_put_contents($path, json_encode($receipt, JSON_THROW_ON_ERROR));
            chmod($path, 0644);
            $this->artisan('homya:confirm-bank-transfer', ['receipt' => $path, '--apply' => true])->assertFailed();
            chmod($path, 0600);
            clearstatcache(true, $path);
            $this->artisan('homya:confirm-bank-transfer', ['receipt' => basename($path), '--apply' => true])->assertFailed();
            file_put_contents($path, json_encode(array_replace($receipt, ['merchant_verified' => false]), JSON_THROW_ON_ERROR));
            $this->artisan('homya:confirm-bank-transfer', ['receipt' => $path, '--apply' => true])->assertFailed();
            file_put_contents($path, json_encode($receipt, JSON_THROW_ON_ERROR));
            $this->artisan('homya:confirm-bank-transfer', ['receipt' => $path])->assertSuccessful();
            $this->assertSame('pending_bank_transfer', $orders->owned($order->id, $order->owner_hash)->status);
            $this->artisan('homya:confirm-bank-transfer', ['receipt' => $path, '--apply' => true])->assertSuccessful();
            $this->artisan('homya:confirm-bank-transfer', ['receipt' => $path, '--apply' => true])->assertSuccessful();
            $this->assertSame('paid', $orders->owned($order->id, $order->owner_hash)->status);
            $this->assertSame(998, $orders->connection()->table('commerce_inventory')->where('slug', 'test-unit')->value('quantity'));
            $this->assertSame(1, $orders->connection()->table('commerce_payment_events')->count());
        } finally {
            unlink($path);
        }
    }
}
