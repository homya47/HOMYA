<?php

namespace App\Services\Commerce;

use App\Services\Admin\Activity;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class OrderStore
{
    public function connection(): Connection
    {
        if (! app()->environment('testing')) {
            $config = config('database.connections.commerce');
            if (empty($config['database']) || empty($config['username']) || ($config['driver'] ?? '') !== 'mariadb'
                || $config['username'] === 'root' || str_ends_with($config['username'], '_reader')) {
                throw new RuntimeException('Commerce configuration unavailable.');
            }
            if (app()->environment('local') && ($config['database'] !== 'homya_local' || $config['username'] !== 'homya_local_commerce'
                || ! in_array($config['host'], ['localhost', '127.0.0.1'], true)
                || ! is_string($config['unix_socket']) || ! str_ends_with($config['unix_socket'], '/mysql.sock')
                || realpath($config['unix_socket']) === false
                || realpath($config['unix_socket']) !== realpath(config('homya.xampp_socket')))) {
                throw new RuntimeException('Local commerce target mismatch.');
            }
        }

        return DB::connection('commerce');
    }

    public function reserve(array $quote, array $customer, string $ownerHash, string $key, string $locale): object
    {
        if (! ($quote['valid'] ?? false) || ! in_array($customer['country'], config('commerce.countries'), true)) {
            $this->reject();
        }
        $snapshot = [];
        foreach ($quote['lines'] as $line) {
            $product = $line['product'];
            $snapshot[] = ['line_key' => $line['key'], 'slug' => $line['slug'], 'reference' => $product['reference'], 'name' => $product['name'],
                'variant_id' => $line['variant_id'], 'variant_name' => $line['variant_name'], 'variant_reference' => $line['variant_reference'],
                'quantity' => $line['quantity'], 'unit_price_cents' => $product['price_cents'], 'line_cents' => $line['line_cents']];
        }
        usort($snapshot, fn ($a, $b) => strcmp($a['line_key'], $b['line_key']));
        $quantities = [];
        foreach ($snapshot as $line) {
            $quantities[$line['slug']] = ($quantities[$line['slug']] ?? 0) + $line['quantity'];
        }
        $hash = hash('sha256', json_encode([$snapshot, $customer, $locale], JSON_THROW_ON_ERROR));
        $db = $this->connection();

        $this->expireBankTransfers();

        return $db->transaction(function () use ($db, $quote, $customer, $ownerHash, $key, $locale, $snapshot, $hash, $quantities) {
            // Lock inventory in stable order, including retries, to serialize simultaneous submissions.
            foreach ($snapshot as $line) {
                $inventory = $db->table('commerce_inventory')->where('slug', $line['slug'])->lockForUpdate()->first();
                $existing = $db->table('commerce_orders')->where('checkout_key', $key)->first();
                if ($existing) {
                    if (! hash_equals($existing->owner_hash, $ownerHash) || ! hash_equals($existing->request_hash, $hash)) {
                        $this->reject();
                    }

                    return $existing;
                }
                if (! $inventory || ! $inventory->sale_enabled || $inventory->reference !== $line['reference']
                    || (int) $inventory->approved_price_cents !== $line['unit_price_cents']
                    || (int) $inventory->quantity - (int) $inventory->reserved < $quantities[$line['slug']]
                    || $inventory->validated_at === null || mb_strlen($line['name']) > 500
                    || mb_strlen($line['variant_name']) > 500 || mb_strlen($line['variant_reference']) > 255) {
                    $this->reject();
                }
            }
            $created = now();
            $id = (string) Str::uuid();
            $bank = ($customer['payment_method'] ?? 'deferred') === 'bank_transfer';
            $deferred = ($customer['payment_method'] ?? 'card') === 'deferred';
            if ($deferred && ! app(CheckoutReadiness::class)->deferredReady()) {
                $this->reject();
            }
            if ($bank) {
                $details = app(BankTransfer::class)->details();
                if ($details === null) {
                    $this->reject();
                }
                $customer['bank_transfer'] = ['details' => $details, 'deadline' => $created->copy()->addHours(BankTransfer::RESERVATION_HOURS)->toIso8601String()];
            }
            $db->table('commerce_orders')->insert([
                'id' => $id, 'owner_hash' => $ownerHash, 'checkout_key' => $key, 'request_hash' => $hash,
                'locale' => $locale, 'country' => $customer['country'], 'currency' => 'EUR',
                'subtotal_cents' => $quote['subtotal_cents'], 'shipping_cents' => 0, 'total_cents' => $quote['total_cents'],
                'customer_encrypted' => Crypt::encryptString(json_encode($customer, JSON_THROW_ON_ERROR)),
                'status' => $deferred ? 'pending_payment_details' : ($bank ? 'pending_bank_transfer' : 'pending_payment'), 'created_at' => $created, 'updated_at' => $created,
            ]);
            foreach ($snapshot as $line) {
                $changed = $db->table('commerce_inventory')->where('slug', $line['slug'])
                    ->whereRaw('quantity - reserved >= ?', [$line['quantity']])->increment('reserved', $line['quantity']);
                if ($changed !== 1) {
                    $this->reject();
                }
                $db->table('commerce_order_items')->insert(['order_id' => $id] + $line);
            }

            app(Activity::class)->record('order_created', $id, $db);

            return $db->table('commerce_orders')->where('id', $id)->first();
        }, 3);
    }

    public function activateDeferredPayment(string $id): object
    {
        $readiness = app(CheckoutReadiness::class);
        if (! $readiness->bankReady()) {
            throw new RuntimeException('Payment instructions unavailable.');
        }
        $this->expireBankTransfers();
        $db = $this->connection();

        return $db->transaction(function () use ($db, $id) {
            $order = $db->table('commerce_orders')->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'pending_payment_details') {
                return $order;
            }
            $deadline = app(BankTransfer::class)->deadline($order);
            if (now()->gte($deadline)) {
                throw new RuntimeException('Reservation expired.');
            }
            $db->table('commerce_orders')->where('id', $id)->update(['status' => 'pending_payment', 'updated_at' => now(),
            ]);

            return $db->table('commerce_orders')->where('id', $id)->firstOrFail();
        }, 3);
    }

    public function switchToBank(string $id, array $details, ?string $source = null): object
    {
        $db = $this->connection();

        return $db->transaction(function () use ($db, $id, $details, $source) {
            $order = $db->table('commerce_orders')->where('id', $id)->lockForUpdate()->firstOrFail();
            if ($order->status === 'pending_bank_transfer') {
                return $order;
            }
            if ($order->status !== 'pending_payment' || now()->gte(app(BankTransfer::class)->deadline($order))) {
                throw new RuntimeException('Bank transition unavailable.');
            }
            $customer = json_decode(Crypt::decryptString($order->customer_encrypted), true, 32, JSON_THROW_ON_ERROR);
            if ($order->stripe_session_id !== null || ($customer['card_attempted'] ?? false)) {
                throw new RuntimeException('Legacy card reconciliation required.');
            }
            $customer['bank_transfer'] = ['details' => $details, 'deadline' => app(BankTransfer::class)->deadline($order)->toIso8601String()];
            $db->table('commerce_orders')->where('id', $id)->update([
                'status' => 'pending_bank_transfer', 'checkout_url' => null,
                'customer_encrypted' => Crypt::encryptString(json_encode($customer, JSON_THROW_ON_ERROR)), 'updated_at' => now(),
            ]);

            return $db->table('commerce_orders')->where('id', $id)->firstOrFail();
        });
    }

    public function confirmBankReceipt(string $id, int $amount, string $reference): object
    {
        if ($reference === '' || strlen($reference) > 200 || $amount < 1) {
            throw new RuntimeException('Receipt invalid.');
        }
        $db = $this->connection();
        $eventId = 'bank_receipt_'.hash('sha256', $reference);
        $payloadHash = hash('sha256', json_encode([$id, $amount, 'EUR', $reference], JSON_THROW_ON_ERROR));

        return $db->transaction(function () use ($db, $id, $amount, $reference, $eventId, $payloadHash) {
            $order = $db->table('commerce_orders')->where('id', $id)->lockForUpdate()->firstOrFail();
            $event = $db->table('commerce_payment_events')->where('stripe_event_id', $eventId)->first();
            if ($event) {
                if ($event->order_id !== $id || ! hash_equals($event->payload_hash, $payloadHash)) {
                    throw new RuntimeException('Receipt already assigned.');
                }

                return $order;
            }
            if ($order->status !== 'pending_bank_transfer' || (int) $order->total_cents !== $amount
                || $order->currency !== 'EUR' || now()->gte(app(BankTransfer::class)->deadline($order))) {
                throw new RuntimeException('Receipt requires manual reconciliation.');
            }
            $customer = json_decode(Crypt::decryptString($order->customer_encrypted), true, 32, JSON_THROW_ON_ERROR);
            if (! isset($customer['bank_transfer'])) {
                throw new RuntimeException('Bank instructions missing.');
            }
            $this->settleInventory($db, $id, true);
            $customer['bank_transfer']['receipt'] = ['bank_reference' => $reference, 'amount_cents' => $amount,
                'confirmed_at' => now()->toIso8601String(), 'method' => 'merchant_verified_private_receipt'];
            $db->table('commerce_orders')->where('id', $id)->update(['status' => 'paid', 'paid_at' => now(),
                'customer_encrypted' => Crypt::encryptString(json_encode($customer, JSON_THROW_ON_ERROR)), 'updated_at' => now()]);
            app(Activity::class)->record('payment_confirmed', $id, $db);
            // Legacy column name retained without schema change; bank events have their own namespace.
            $db->table('commerce_payment_events')->insert(['stripe_event_id' => $eventId, 'order_id' => $id,
                'payload_hash' => $payloadHash, 'outcome' => 'bank_paid', 'created_at' => now()]);

            return $db->table('commerce_orders')->where('id', $id)->firstOrFail();
        }, 3);
    }

    public function expireBankTransfers(int $limit = 200): int
    {
        $db = $this->connection();
        $ids = $db->table('commerce_orders')->whereIn('status', ['pending_bank_transfer', 'pending_payment_details'])
            ->where('created_at', '<=', now()->subHours(BankTransfer::RESERVATION_HOURS))->orderBy('id')->limit($limit)->pluck('id');
        $expired = 0;
        foreach ($ids as $id) {
            $expired += $db->transaction(function () use ($db, $id) {
                $order = $db->table('commerce_orders')->where('id', $id)->lockForUpdate()->firstOrFail();
                if (! in_array($order->status, ['pending_bank_transfer', 'pending_payment_details'], true) || now()->lt(app(BankTransfer::class)->deadline($order))) {
                    return 0;
                }
                $this->settleInventory($db, $id, false);
                $db->table('commerce_orders')->where('id', $id)->update(['status' => $order->status === 'pending_payment_details' ? 'expired' : 'bank_transfer_expired', 'updated_at' => now()]);

                return 1;
            }, 3);
        }

        return $expired;
    }

    public function owned(string $id, string $ownerHash): object
    {
        $order = $this->connection()->table('commerce_orders')->where('id', $id)->where('owner_hash', $ownerHash)->first();
        abort_unless($order, 404);

        return $order;
    }

    public function items(string $id): array
    {
        return $this->connection()->table('commerce_order_items')->where('order_id', $id)->orderBy('slug')->get()->all();
    }

    private function settleInventory(Connection $db, string $id, bool $paid): void
    {
        foreach ($db->table('commerce_order_items')->where('order_id', $id)->orderBy('slug')->get() as $item) {
            $row = $db->table('commerce_inventory')->where('slug', $item->slug)->lockForUpdate()->first();
            if (! $row || (int) $row->reserved < $item->quantity || (int) $row->quantity < $item->quantity) {
                throw new RuntimeException('Stock ledger conflict.');
            }
            $db->table('commerce_inventory')->where('slug', $item->slug)->update([
                'reserved' => $row->reserved - $item->quantity,
                'quantity' => $row->quantity - ($paid ? $item->quantity : 0), 'updated_at' => now(),
            ]);
        }
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['cart' => __('commerce.inventory_changed')]);
    }
}
