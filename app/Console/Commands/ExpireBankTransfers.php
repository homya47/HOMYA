<?php

namespace App\Console\Commands;

use App\Services\Commerce\BankTransfer;
use App\Services\Commerce\OrderStore;
use Illuminate\Console\Command;
use Throwable;

final class ExpireBankTransfers extends Command
{
    protected $signature = 'homya:expire-bank-transfers {--apply : Release bank and deferred reservations past the confirmed 72-hour deadline}';

    protected $description = 'Check overdue bank and deferred orders; release their stock only with --apply.';

    public function handle(OrderStore $orders): int
    {
        try {
            if ($this->option('apply')) {
                $count = $orders->expireBankTransfers();
                $this->line(json_encode(['expired_orders' => $count, 'reservation_hours' => BankTransfer::RESERVATION_HOURS, 'applied' => true], JSON_THROW_ON_ERROR));
            } else {
                $count = $orders->connection()->table('commerce_orders')->whereIn('status', ['pending_bank_transfer', 'pending_payment_details'])
                    ->where('created_at', '<=', now()->subHours(BankTransfer::RESERVATION_HOURS))->count();
                $this->line(json_encode(['overdue_orders' => $count, 'reservation_hours' => BankTransfer::RESERVATION_HOURS, 'applied' => false], JSON_THROW_ON_ERROR));
            }

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Bank reservation check unavailable. No private details displayed.');

            return self::FAILURE;
        }
    }
}
