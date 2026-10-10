<?php

use App\Services\Admin\Deliveries;
use App\Services\Commerce\OrderStore;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// Authorized local acceptance: all business fixtures are rolled back.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
function ensureAdmin(bool $condition): void
{
    if (! $condition) {
        throw new RuntimeException('LOCAL_ADMIN_ACCEPTANCE_FAILED');
    }
}
$db = null;
try {
    ensureAdmin(app()->environment('local') && config('dashboard.enabled') === true);
    Http::preventStrayRequests();
    $db = app(OrderStore::class)->connection();
    $identity = $db->selectOne('SELECT VERSION() AS version, DATABASE() AS db, @@datadir AS datadir');
    ensureAdmin($identity->db === 'homya_local' && $identity->version === '10.4.28-MariaDB'
        && realpath($identity->datadir) === '/Applications/XAMPP/xamppfiles/var/mysql');
    $tables = ['commerce_inventory', 'commerce_orders', 'commerce_order_items', 'commerce_payment_events', 'commerce_admin_shipments'];
    $fingerprints = function () use ($db, $tables): array {
        $values = [];
        foreach ($tables as $table) {
            $values[$table] = hash('sha256', json_encode($db->table($table)->orderBy($table === 'commerce_inventory' ? 'slug' : ($table === 'commerce_admin_shipments' ? 'order_id' : ($table === 'commerce_payment_events' ? 'stripe_event_id' : 'id')))->get()->all(), JSON_THROW_ON_ERROR));
        }

        return $values;
    };
    $before = $fingerprints();
    $beforeOrders = $db->table('commerce_orders')->count();
    $beforeReserved = (int) $db->table('commerce_inventory')->sum('reserved');
    $db->beginTransaction();
    $slug = 'qa-management-'.Str::uuid();
    $db->table('commerce_inventory')->insert(['slug' => $slug, 'reference' => 'RECETTE-ROLLBACK', 'quantity' => 10, 'reserved' => 2, 'approved_price_cents' => 100, 'sale_enabled' => false, 'validated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    $makeOrder = function (string $status) use ($db): string {
        $id = (string) Str::uuid();
        $db->table('commerce_orders')->insert(['id' => $id, 'owner_hash' => hash('sha256', $id), 'checkout_key' => Str::uuid(), 'request_hash' => hash('sha256', 'fixture'.$id), 'locale' => 'fr', 'country' => 'FR', 'currency' => 'EUR', 'subtotal_cents' => 200, 'shipping_cents' => 0, 'total_cents' => 200, 'customer_encrypted' => Crypt::encryptString('{"name":"RECETTE ROLLBACK"}'), 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    };
    $pending = $makeOrder('pending_payment_details');
    $db->table('commerce_order_items')->insert(['order_id' => $pending, 'slug' => $slug, 'line_key' => $slug, 'reference' => 'RECETTE-ROLLBACK', 'name' => 'RECETTE FICTIVE', 'quantity' => 2, 'unit_price_cents' => 100, 'line_cents' => 200]);
    $deliveries = app(Deliveries::class);
    try {
        $deliveries->save($pending, ['revision' => 0, 'status' => 'processing']);
        throw new RuntimeException('UNPAID_SHIPMENT_ACCEPTED');
    } catch (ValidationException) {
    }
    $deliveries->cancel($pending);
    $deliveries->cancel($pending);
    $row = $db->table('commerce_inventory')->where('slug', $slug)->first();
    ensureAdmin((int) $row->quantity === 10 && (int) $row->reserved === 0 && $db->table('commerce_orders')->where('id', $pending)->value('status') === 'cancelled');
    // Artificial paid fixture exists only inside this transaction: no charge or receipt.
    $paid = $makeOrder('paid');
    $deliveries->save($paid, ['revision' => 0, 'status' => 'processing']);
    try {
        $deliveries->save($paid, ['revision' => 1, 'status' => 'shipped', 'carrier' => 'RECETTE', 'tracking_number' => 'FICTIF']);
        throw new RuntimeException('UNCONFIRMED_SHIPMENT_ACCEPTED');
    } catch (ValidationException) {
    }
    $values = ['revision' => 1, 'status' => 'shipped', 'carrier' => 'RECETTE FICTIVE', 'tracking_number' => 'RECETTE-ROLLBACK', 'tracking_url' => 'https://example.invalid/fixture', 'confirmed' => true];
    $deliveries->save($paid, $values);
    try {
        $deliveries->save($paid, $values);
        throw new RuntimeException('STALE_REVISION_ACCEPTED');
    } catch (ValidationException) {
    }
    $values['revision'] = 2;
    $values['status'] = 'delivered';
    $deliveries->save($paid, $values);
    ensureAdmin($deliveries->get($paid)->status === 'delivered' && (int) $deliveries->get($paid)->revision === 3);
    try {
        $deliveries->cancel($paid);
        throw new RuntimeException('PAID_CANCELLATION_ACCEPTED');
    } catch (ValidationException) {
    }
    ensureAdmin($db->table('commerce_admin_events')->where('subject', $pending)->where('type', 'order_cancelled')->count() === 1
        && $db->table('commerce_admin_events')->where('subject', $paid)->where('type', 'shipment_updated')->count() === 3);
    $db->rollBack();
    ensureAdmin($fingerprints() === $before && $db->table('commerce_orders')->count() === $beforeOrders
        && (int) $db->table('commerce_inventory')->sum('reserved') === $beforeReserved);
    $result = ['result' => 'passed', 'mariadb' => $identity->version, 'unpaid_delivery_refused' => true, 'cancellation_idempotent' => true, 'total_stock_preserved' => true, 'confirmation_required' => true, 'revision_guard' => true, 'delivery_progression' => true, 'paid_cancellation_refused' => true, 'events_once' => true, 'rollback_verified' => true, 'existing_orders' => $beforeOrders, 'existing_reservations' => $beforeReserved, 'external_calls' => false];
    file_put_contents(base_path('output/qa-commerce/admin-management-mariadb.json'), json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $error) {
    if ($db) {
        while ($db->transactionLevel() > 0) {
            $db->rollBack();
        }
    }
    echo json_encode(['result' => 'failed', 'error_class' => get_class($error), 'details' => 'withheld'])."\n";
    exit(1);
}
