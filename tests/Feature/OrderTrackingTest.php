<?php

namespace Tests\Feature;

use App\Services\Commerce\OrderTracking;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderTrackingTest extends TestCase
{
    private const REFERENCE = 'b3c76983-6741-4dc2-a93f-b0c7d327386a';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['dashboard.enabled' => true, 'database.connections.commerce' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        DB::purge('commerce');
        Schema::connection('commerce')->create('commerce_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('status');
            $table->string('owner_hash');
            $table->text('customer_encrypted');
            $table->timestamps();
        });
        Schema::connection('commerce')->create('commerce_admin_shipments', function (Blueprint $table) {
            $table->uuid('order_id')->primary();
            $table->string('status');
            $table->string('tracking_number');
        });
        DB::connection('commerce')->table('commerce_orders')->insert([
            'id' => self::REFERENCE, 'status' => 'pending_payment_details',
            'owner_hash' => 'owner-private-fixture', 'customer_encrypted' => 'customer-private-fixture',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_tracking_form_is_localized_and_private(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->get('/'.$locale.'/suivi')->assertOk()
                ->assertSee(__('tracking.title', [], $locale))
                ->assertSee('name="reference"', false)
                ->assertSee('method="post"', false)
                ->assertDontSee(self::REFERENCE)
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }
    }

    public function test_actual_order_and_shipment_states_are_returned_without_customer_data_or_access_grants(): void
    {
        foreach (['pending_payment_details' => 'registered', 'paid' => 'paid', 'cancelled' => 'cancelled', 'expired' => 'expired', 'bank_transfer_expired' => 'expired'] as $status => $stage) {
            DB::connection('commerce')->table('commerce_orders')->update(['status' => $status]);
            $this->assertSame(['reference' => self::REFERENCE, 'stage' => $stage], app(OrderTracking::class)->find(self::REFERENCE));
        }
        DB::connection('commerce')->table('commerce_orders')->update(['status' => 'paid']);
        foreach (['processing', 'shipped', 'delivered'] as $stage) {
            DB::connection('commerce')->table('commerce_admin_shipments')->updateOrInsert(['order_id' => self::REFERENCE], ['status' => $stage, 'tracking_number' => 'carrier-private-fixture']);
            foreach (['fr', 'en', 'de'] as $locale) {
                $this->withSession(['commerce.owner' => 'outsider'])->post('/'.$locale.'/suivi', ['reference' => strtoupper(self::REFERENCE)])
                    ->assertOk()->assertSee(__('tracking.stages.'.$stage, [], $locale))
                    ->assertDontSee('owner-private-fixture')->assertDontSee('customer-private-fixture')
                    ->assertDontSee('carrier-private-fixture')->assertDontSee('/commande/'.self::REFERENCE, false);
                $this->assertSame('outsider', session('commerce.owner'));
                $this->assertNull(session('commerce.last_order'));
            }
        }
        DB::connection('commerce')->table('commerce_orders')->update(['status' => 'pending_payment_details']);
        $this->assertSame('registered', app(OrderTracking::class)->find(self::REFERENCE)['stage']);
    }

    public function test_malformed_and_unknown_references_have_the_same_generic_response(): void
    {
        foreach (['1', '<script>alert(1)</script>', 'b3c76983-6741-4dc2-a93f-b0c7d327386b', ['reference'], str_repeat('a', 1000)] as $reference) {
            $this->post('/fr/suivi', ['reference' => $reference])->assertOk()
                ->assertSee(__('tracking.not_found'))->assertDontSee(self::REFERENCE)
                ->assertDontSee('<script>alert(1)</script>', false);
        }
    }

    public function test_repeated_tracking_requests_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/fr/suivi', ['reference' => 'invalid'])->assertOk();
        }
        $this->post('/fr/suivi', ['reference' => 'invalid'])->assertStatus(429);
    }
}
