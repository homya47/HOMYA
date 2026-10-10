<?php

namespace App\Services\Admin;

use App\Services\Commerce\OrderStore;
use Illuminate\Database\Connection;

final class Activity
{
    public const LABELS = ['page_view' => 'Page consultée', 'product_view' => 'Produit consulté', 'cart_add' => 'Ajout au panier', 'checkout_view' => 'Commande ouverte', 'order_created' => 'Commande enregistrée', 'payment_confirmed' => 'Paiement confirmé', 'message_client' => 'Message client', 'message_admin' => 'Réponse publiée', 'return_received' => 'Déclaration de retour', 'product_saved' => 'Produit enregistré', 'settings_saved' => 'Paramètres enregistrés', 'shipment_updated' => 'Livraison mise à jour', 'order_cancelled' => 'Commande annulée', 'document_issued' => 'Document commercial émis'];

    public const TRAFFIC = ['page_view', 'product_view', 'cart_add', 'checkout_view'];

    public function record(string $type, string $subject, ?Connection $db = null, ?string $key = null): void
    {
        if (! config('dashboard.enabled')) {
            return;
        }
        $db ??= app(OrderStore::class)->connection();
        $id = hash('sha256', $type.'|'.($key ?? $subject));
        $db->table('commerce_admin_events')->insertOrIgnore(['id' => $id, 'type' => $type, 'subject' => mb_substr($subject, 0, 255), 'count' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function aggregate(string $type, string $subject): void
    {
        if (! config('dashboard.enabled')) {
            return;
        }
        $db = app(OrderStore::class)->connection();
        $id = hash('sha256', $type.'|'.now()->utc()->toDateString().'|'.$subject);
        $db->transaction(function () use ($db, $type, $subject, $id) {
            $db->table('commerce_admin_events')->insertOrIgnore(['id' => $id, 'type' => $type, 'subject' => $subject, 'count' => 0, 'created_at' => now()->utc()->startOfDay(), 'updated_at' => now()]);
            $db->table('commerce_admin_events')->where('id', $id)->increment('count', 1, ['updated_at' => now()]);
        });
    }

    public function notifications(int $limit = 20)
    {
        return app(OrderStore::class)->connection()->table('commerce_admin_events')->whereNotIn('type', self::TRAFFIC)->orderByDesc('created_at')->orderByDesc('id')->limit($limit)->get();
    }

    public function counts(): array
    {
        $db = app(OrderStore::class)->connection();

        return ['messages' => $db->table('commerce_admin_threads')->where('unread', true)->count(), 'notifications' => $db->table('commerce_admin_events')->whereNotIn('type', self::TRAFFIC)->whereNull('read_at')->count()];
    }

    public function traffic(int $days = 14): array
    {
        $db = app(OrderStore::class)->connection();
        $since = now()->utc()->startOfDay()->subDays($days - 1);

        return ['since' => $since, 'until' => now()->utc(), 'rows' => $db->table('commerce_admin_events')->whereIn('type', self::TRAFFIC)->where('created_at', '>=', $since)->selectRaw('DATE(created_at) as day, type, SUM(count) as total')->groupByRaw('DATE(created_at), type')->orderByDesc('day')->get(), 'notifications' => $this->notifications(100)];
    }
}
