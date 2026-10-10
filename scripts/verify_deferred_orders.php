<?php

use App\Services\Commerce\BankTransfer;
use App\Services\Commerce\Cart;
use App\Services\Commerce\CheckoutReadiness;
use App\Services\Commerce\OrderStore;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

// Local acceptance only: no persistent order, no external provider, no stock reset.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
function ensureDeferred(bool $valid): void
{
    if (! $valid) {
        throw new RuntimeException('Deferred order acceptance failed.');
    }
}
$db = null;
try {
    $readiness = app(CheckoutReadiness::class);
    ensureDeferred(app()->environment('local') && $readiness->deferredReady()
        && ! $readiness->cardReady() && ! $readiness->bankReady() && config('commerce.legal_ready') === false);
    $orders = app(OrderStore::class);
    $db = $orders->connection();
    $identity = $db->selectOne('SELECT VERSION() AS version, DATABASE() AS db, @@datadir AS datadir');
    ensureDeferred($identity->db === 'homya_local' && str_contains($identity->version, '10.4.28-MariaDB')
        && realpath($identity->datadir) === realpath(config('homya.xampp_datadir')));
    $counts = fn () => array_map(fn ($table) => $db->table($table)->count(), ['commerce_orders', 'commerce_order_items', 'commerce_payment_events']);
    $beforeCounts = $counts();
    $fingerprint = fn () => hash('sha256', json_encode($db->table('commerce_inventory')->orderBy('slug')->get()->all(), JSON_THROW_ON_ERROR));
    $before = $fingerprint();
    $db->beginTransaction();
    $session = new Store('deferred-fixture', new ArraySessionHandler(120));
    $session->start();
    $slug = 'chauffage-363102-poeles-a-bois';
    $previous = $db->table('commerce_inventory')->where('slug', $slug)->firstOrFail();
    $cart = app(Cart::class);
    $cart->put($session, $slug, 1, false, 'v1');
    $cart->put($session, $slug, 1, false, 'v2');
    $quote = $cart->quote($session);
    ensureDeferred($quote['valid'] && count($quote['lines']) === 2);
    $customer = ['name' => 'Fixture locale', 'email' => 'fixture@example.invalid', 'address' => 'Adresse fictive',
        'postal_code' => '75001', 'city' => 'Test', 'country' => 'FR', 'payment_method' => 'deferred'];
    $owner = hash('sha256', 'local-deferred-fixture');
    $key = (string) Str::uuid();
    $order = $orders->reserve($quote, $customer, $owner, $key, 'fr');
    ensureDeferred($order->status === 'pending_payment_details' && $order->stripe_session_id === null && $order->paid_at === null
        && (int) $order->total_cents === $quote['total_cents'] && ! str_contains($order->customer_encrypted, $customer['email']));
    ensureDeferred($orders->reserve($quote, $customer, $owner, $key, 'fr')->id === $order->id);
    ensureDeferred((int) $db->table('commerce_inventory')->where('slug', $slug)->value('reserved') === (int) $previous->reserved + 2);
    $deadline = app(BankTransfer::class)->deadline($order);
    Carbon::setTestNow($deadline->copy()->subSecond());
    $orders->expireBankTransfers();
    ensureDeferred($orders->owned($order->id, $owner)->status === 'pending_payment_details');
    Carbon::setTestNow($deadline);
    $orders->expireBankTransfers();
    ensureDeferred($orders->owned($order->id, $owner)->status === 'expired');
    $afterExpiry = $db->table('commerce_inventory')->where('slug', $slug)->firstOrFail();
    ensureDeferred((int) $afterExpiry->quantity === (int) $previous->quantity && (int) $afterExpiry->reserved === (int) $previous->reserved);
    $orders->expireBankTransfers();
    ensureDeferred((int) $db->table('commerce_inventory')->where('slug', $slug)->value('reserved') === (int) $previous->reserved);
    $db->rollBack();
    Carbon::setTestNow();
    ensureDeferred($counts() === $beforeCounts && hash_equals($before, $fingerprint()));
    $result = ['result' => 'passed', 'mariadb' => $identity->version, 'deferred_order' => true, 'shared_variants' => true,
        'idempotency' => true, 'reservation_72h' => true, 'expiry_released_once' => true, 'no_charge' => true,
        'rollback_verified' => true, 'counts_before' => $beforeCounts, 'counts_after' => $counts(),
        'inventory_sha256_before' => $before, 'inventory_sha256_after' => $fingerprint()];
    file_put_contents(base_path('output/qa-commerce/deferred-mariadb.json'), json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    if ($db) {
        while ($db->transactionLevel() > 0) {
            $db->rollBack();
        }
    }
    Carbon::setTestNow();
    echo json_encode(['result' => 'failed', 'error_class' => get_class($error), 'details' => 'withheld'])."\n";
    exit(1);
}
