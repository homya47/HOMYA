<?php

namespace App\Console\Commands;

use App\Services\Commerce\OrderStore;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

final class ConfirmBankTransfer extends Command
{
    protected $signature = 'homya:confirm-bank-transfer {receipt : Absolute path to a private, merchant-verified JSON receipt} {--apply : Confirm a verified receipt and settle stock once}';

    protected $description = 'Preview or apply a bank receipt verified by HOMYA. Never accepts a customer declaration.';

    public function handle(OrderStore $orders): int
    {
        try {
            $path = $this->argument('receipt');
            $parent = storage_path('app/private/commerce/receipts');
            if (! is_dir($parent) || ! is_string($path) || ! str_starts_with($path, '/') || is_link($path)
                || realpath(dirname($path)) !== realpath($parent) || ! is_file($path) || filesize($path) > 8192
                || (fileperms($path) & 0077) !== 0 || fileowner($path) !== posix_geteuid()) {
                throw new \RuntimeException;
            }
            $receipt = json_decode(file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
            if (count($receipt) !== 5 || ! Str::isUuid($receipt['order_id'] ?? '') || ! is_int($receipt['amount_cents'] ?? null)
                || ($receipt['currency'] ?? '') !== 'EUR' || ($receipt['merchant_verified'] ?? false) !== true
                || ! is_string($receipt['bank_reference'] ?? null) || trim($receipt['bank_reference']) === '') {
                throw new \RuntimeException;
            }
            $order = $orders->connection()->table('commerce_orders')->where('id', $receipt['order_id'])->firstOrFail();
            if ((int) $order->total_cents !== $receipt['amount_cents']) {
                throw new \RuntimeException;
            }
            if ($this->option('apply')) {
                $orders->confirmBankReceipt($receipt['order_id'], $receipt['amount_cents'], trim($receipt['bank_reference']));
            }
            $this->line(json_encode(['receipt_amount_matches' => true, 'applied' => (bool) $this->option('apply')], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Bank receipt refused or unavailable. No private details displayed.');

            return self::FAILURE;
        }
    }
}
