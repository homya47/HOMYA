<?php

namespace App\Services\Commerce;

use Throwable;

final class InventoryAvailability
{
    public function __construct(private OrderStore $orders) {}

    private bool $expiredChecked = false;

    public function available(array $product): ?int
    {
        if (config('commerce.inventory_enabled') !== true) {
            return null;
        }
        try {
            if (! $this->expiredChecked) {
                $this->orders->expireBankTransfers();
                $this->expiredChecked = true;
            }
            $row = $this->orders->connection()->table('commerce_inventory')->where('slug', $product['slug'])->first();
            if (! $row || ! $row->sale_enabled || $row->reference !== $product['reference']
                || (int) $row->approved_price_cents !== $product['price_cents'] || $row->validated_at === null
                || (int) $row->reserved > (int) $row->quantity) {
                return null;
            }

            return (int) $row->quantity - (int) $row->reserved;
        } catch (Throwable) {
            return null;
        }
    }
}
