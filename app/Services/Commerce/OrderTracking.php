<?php

namespace App\Services\Commerce;

use App\Services\Admin\Deliveries;

final class OrderTracking
{
    public function find(string $reference): ?array
    {
        // The full random UUID is the tracking reference; never return customer or payment data.
        if (! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $reference)) {
            return null;
        }
        $order = app(OrderStore::class)->connection()->table('commerce_orders')
            ->select(['id', 'status', 'created_at', 'updated_at'])
            ->where('id', strtolower($reference))->first();
        if (! $order) {
            return null;
        }
        $stage = $order->status === 'paid' ? 'paid' : 'registered';
        if (in_array($order->status, ['cancelled', 'expired', 'bank_transfer_expired'], true)) {
            $stage = $order->status === 'cancelled' ? 'cancelled' : 'expired';
        }
        if ($order->status === 'paid') {
            $shipment = app(Deliveries::class)->get($order->id);
            if ($shipment && in_array($shipment->status, ['processing', 'shipped', 'delivered'], true)) {
                $stage = $shipment->status;
            }
        }

        return ['reference' => $order->id, 'stage' => $stage];
    }
}
