<?php

namespace App\Console\Commands;

use App\Services\Commerce\OrderStore;
use Illuminate\Console\Command;
use Throwable;

final class PrepareCommerceSchema extends Command
{
    protected $signature = 'homya:commerce-schema {--apply : Execute a separately authorized local schema plan} {--sha256= : Exact reviewed migration digest} {--resume-empty-schema : Explicit completion of the inspected two-table empty state}';

    protected $description = 'Show the four-table commerce schema plan without SQL; local execution needs dedicated authorization.';

    public function handle(OrderStore $orders): int
    {
        $path = database_path('migrations/commerce/2026_10_09_000001_create_commerce_tables.php');
        $tables = ['commerce_inventory', 'commerce_orders', 'commerce_order_items', 'commerce_payment_events'];
        $hash = hash_file('sha256', $path);
        if (! $this->option('apply')) {
            $this->line(json_encode(['target' => 'XAMPP MariaDB / homya_local', 'tables' => $tables,
                'migration_sha256' => $hash, 'sql_executed' => false], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }
        try {
            $config = config('database.connections.commerce');
            if (! app()->environment('local') || ! hash_equals($hash, (string) $this->option('sha256'))
                || $config['database'] !== 'homya_local' || $config['username'] !== 'homya_local_commerce'
                || ! in_array($config['host'], ['localhost', '127.0.0.1'], true)
                || realpath($config['unix_socket']) !== realpath(config('homya.xampp_socket'))
                || ! is_file($path)) {
                throw new \RuntimeException;
            }
            $db = $orders->connection();
            $identity = $db->selectOne('SELECT VERSION() AS version, DATABASE() AS db, @@datadir AS datadir');
            if ($identity->db !== 'homya_local' || ! str_contains($identity->version, '10.4.28-MariaDB')
                || realpath($identity->datadir) !== realpath(config('homya.xampp_datadir'))) {
                throw new \RuntimeException;
            }
            if ($this->option('resume-empty-schema')) {
                // Complete only the observed first two empty CREATEs. Never generic migration retry.
                $present = array_values(array_filter($tables, fn ($table) => $db->getSchemaBuilder()->hasTable($table)));
                if ($present !== array_slice($tables, 0, 2)) {
                    throw new \RuntimeException;
                }
                foreach ($present as $table) {
                    if ($db->table($table)->count() !== 0) {
                        throw new \RuntimeException;
                    }
                }
                $expectedColumns = [
                    'commerce_inventory' => ['slug', 'reference', 'quantity', 'reserved', 'approved_price_cents', 'sale_enabled', 'validated_at', 'created_at', 'updated_at'],
                    'commerce_orders' => ['id', 'owner_hash', 'checkout_key', 'request_hash', 'locale', 'country', 'currency', 'subtotal_cents', 'shipping_cents', 'total_cents', 'customer_encrypted', 'status', 'stripe_session_id', 'stripe_payment_id', 'checkout_url', 'paid_at', 'created_at', 'updated_at'],
                ];
                foreach ($expectedColumns as $table => $columns) {
                    $actual = $db->select('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [$table]);
                    $indexes = $db->getSchemaBuilder()->getIndexes($table);
                    if (array_column($actual, 'COLUMN_NAME') !== $columns || count($indexes) !== 1 || ! $indexes[0]['primary']) {
                        throw new \RuntimeException;
                    }
                }
                $queries = $db->pretend(fn () => (require $path)->up());
                foreach ($queries as $query) {
                    if (str_starts_with($query['query'], 'create table `commerce_inventory`') || str_starts_with($query['query'], 'create table `commerce_orders`')) {
                        continue;
                    }
                    $db->statement($query['query'], $query['bindings']);
                }
            } else {
                foreach ($tables as $table) {
                    if ($db->getSchemaBuilder()->hasTable($table)) {
                        throw new \RuntimeException;
                    }
                }
                (require $path)->up();
            }
            foreach ($tables as $table) {
                if (! $db->getSchemaBuilder()->hasTable($table) || $db->table($table)->count() !== 0) {
                    throw new \RuntimeException;
                }
            }
            $this->info('Four empty commerce tables created and checked. No catalogue mutation or stock import.');

            return self::SUCCESS;
        } catch (Throwable) {
            // MariaDB DDL is not transactional. Stop, preserve evidence, never drop or automatically retry.
            $this->error('Schema refused or stopped. Inspect partial DDL and prepare a bounded recovery before any explicit continuation.');

            return self::FAILURE;
        }
    }
}
