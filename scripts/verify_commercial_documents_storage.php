<?php

// Read-only live checks; never publishes/allocates a number or changes commerce records.
use App\Services\Commerce\OrderStore;
use App\Services\Documents\DocumentStore;
use Illuminate\Contracts\Console\Kernel;

$target = '/Applications/XAMPP/xamppfiles/htdocs/HOMYA';
require $target.'/vendor/autoload.php';
$app = require $target.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
try {
    if (! $app->environment('local') || ! config('dashboard.enabled')) {
        throw new RuntimeException('Local dashboard required.');
    }
    $db = app(OrderStore::class)->connection();
    $inventory = $db->table('commerce_inventory');
    $data = ['zero_stock_items' => (clone $inventory)->where('quantity', 0)->count(), 'inventory_total' => (clone $inventory)->count(), 'stock_total' => (int) (clone $inventory)->sum('quantity'), 'reserved_total' => (int) (clone $inventory)->sum('reserved'), 'orders' => $db->table('commerce_orders')->count(), 'paid_orders' => $db->table('commerce_orders')->where('status', 'paid')->count(), 'shipments' => $db->table('commerce_admin_shipments')->count()];
    $documents = app(DocumentStore::class)->all();
    $data['documents'] = count($documents);
    $data['issued_documents'] = count(array_filter($documents, fn ($record) => $record['status'] === 'issued'));
    $data['recipe_drafts'] = count(array_filter($documents, fn ($record) => $record['status'] === 'draft' && str_starts_with($record['data']['customer']['name'], 'RECETTE DOCUMENTS ')));
    echo json_encode(['result' => 'checked', 'live_read_only' => true, 'data' => $data]);
} catch (Throwable) {
    echo json_encode(['result' => 'unavailable', 'private_errors_hidden' => true]);
    exit(1);
}
