<?php

namespace App\Services\Admin;

use App\Services\Commerce\OrderStore;
use Illuminate\Validation\ValidationException;

final class Deliveries
{
    public const LABELS = ['processing' => 'En préparation', 'shipped' => 'Expédiée', 'delivered' => 'Livrée'];

    public function get(string $id): ?object
    {
        if (! config('dashboard.enabled')) {
            return null;
        }

        return app(OrderStore::class)->connection()->table('commerce_admin_shipments')->where('order_id', $id)->first();
    }

    public function save(string $id, array $data): void
    {
        $db = app(OrderStore::class)->connection();
        $db->transaction(function () use ($db, $id, $data) {
            $order = $db->table('commerce_orders')->where('id', $id)->lockForUpdate()->first();
            abort_unless($order, 404);
            if ($order->status !== 'paid') {
                throw ValidationException::withMessages(['status' => 'Le paiement doit être confirmé avant de préparer une expédition.']);
            }
            $previous = $db->table('commerce_admin_shipments')->where('order_id', $id)->lockForUpdate()->first();
            if (($previous->revision ?? 0) !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => 'La livraison a été modifiée. Rechargez la page.']);
            }
            $allowed = ['' => ['processing'], 'processing' => ['processing', 'shipped'], 'shipped' => ['shipped', 'delivered'], 'delivered' => ['delivered']];
            if (! in_array($data['status'], $allowed[$previous->status ?? ''], true)) {
                throw ValidationException::withMessages(['status' => 'Respectez les étapes : préparation, expédition, livraison.']);
            }
            if (in_array($data['status'], ['shipped', 'delivered'], true) && (trim($data['carrier'] ?? '') === '' || trim($data['tracking_number'] ?? '') === '' || empty($data['confirmed']))) {
                throw ValidationException::withMessages(['confirmed' => 'Confirmez l’événement réel et renseignez transporteur et numéro de suivi.']);
            }
            $values = ['status' => $data['status'], 'carrier' => $data['carrier'] ?? '', 'tracking_number' => $data['tracking_number'] ?? '', 'tracking_url' => $data['tracking_url'] ?? '', 'revision' => ($previous->revision ?? 0) + 1, 'updated_at' => now()];
            if (! $previous) {
                $values['created_at'] = now();
            }
            $db->table('commerce_admin_shipments')->updateOrInsert(['order_id' => $id], $values);
            app(Activity::class)->record('shipment_updated', $id, $db, $id.'|'.$values['revision']);
        }, 3);
    }

    public function cancel(string $id): void
    {
        $db = app(OrderStore::class)->connection();
        $db->transaction(function () use ($db, $id) {
            $order = $db->table('commerce_orders')->where('id', $id)->lockForUpdate()->first();
            abort_unless($order, 404);
            if ($order->status === 'cancelled') {
                return;
            }
            if (! in_array($order->status, ['pending_payment_details', 'pending_bank_transfer'], true) || $order->stripe_session_id !== null) {
                throw ValidationException::withMessages(['order' => 'Seule une commande impayée sans tentative de paiement par carte en attente peut être annulée ici.']);
            }
            $items = $db->table('commerce_order_items')->where('order_id', $id)->orderBy('slug')->get()->groupBy('slug');
            foreach ($items as $slug => $lines) {
                $inventory = $db->table('commerce_inventory')->where('slug', $slug)->lockForUpdate()->first();
                $quantity = $lines->sum('quantity');
                if (! $inventory || $inventory->reserved < $quantity) {
                    throw new \RuntimeException('RESERVATION_MISMATCH');
                }
                $db->table('commerce_inventory')->where('slug', $slug)->decrement('reserved', $quantity, ['updated_at' => now()]);
            }
            $db->table('commerce_orders')->where('id', $id)->update(['status' => 'cancelled', 'updated_at' => now()]);
            app(Activity::class)->record('order_cancelled', $id, $db);
        }, 3);
    }
}
