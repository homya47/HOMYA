<?php

namespace App\Services\Commerce;

use App\Services\Catalogue;
use RuntimeException;

final class InitialInventory
{
    public function prepare(Catalogue $catalogue): array
    {
        app()->setLocale('fr');
        $products = $catalogue->products();
        if ($products->isEmpty() || $catalogue->sourceUnavailable() || $catalogue->translationUnavailable()) {
            throw new RuntimeException('Catalogue unavailable.');
        }
        $references = $products->pluck('reference')->countBy();
        $cart = new Cart($catalogue);
        $rows = [];
        $counts = [];
        foreach ($products->sortBy('slug') as $product) {
            if ($product['has_variants']) {
                $product = $catalogue->product($product['slug']);
                if ($product === null) {
                    throw new RuntimeException('Variant detail unavailable.');
                }
            }
            $blocks = [];
            if (! $cart->eligible($product)) {
                $blocks[] = $product['has_variants'] ? 'variant_offer_not_defined' : 'invalid_price_or_currency';
            }
            if (! is_string($product['reference']) || trim($product['reference']) === '' || mb_strlen($product['reference']) > 255) {
                $blocks[] = 'invalid_reference';
            } elseif (($references[$product['reference']] ?? 0) > 1) {
                $blocks[] = 'ambiguous_reference';
            }
            if (mb_strlen($product['name']) > 500) {
                $blocks[] = 'name_too_long';
            }
            foreach ($blocks as $block) {
                $counts[$block] = ($counts[$block] ?? 0) + 1;
            }
            $rows[] = ['slug' => $product['slug'], 'reference' => $product['reference'],
                'quantity' => 1000, 'approved_price_cents' => $product['price_cents'],
                'sale_enabled' => $blocks === [], 'blocks' => $blocks,
                'catalogue_review_flags' => $product['issues']];
        }

        return ['version' => 1, 'source' => 'user_declared_initial_quantity_1000',
            'currency' => 'EUR', 'prices_include_tax' => true, 'prepared_at' => now()->toIso8601String(),
            'summary' => ['products' => count($rows), 'sale_eligible' => count(array_filter($rows, fn ($row) => $row['sale_enabled'])),
                'initial_units' => count($rows) * 1000, 'blocking_reasons' => $counts], 'products' => $rows];
    }

    public function install(array $manifest, Catalogue $catalogue, OrderStore $orders): array
    {
        $expected = $this->prepare($catalogue);
        if (($manifest['version'] ?? null) !== 1 || ($manifest['source'] ?? null) !== $expected['source']
            || ($manifest['currency'] ?? null) !== 'EUR' || ($manifest['prices_include_tax'] ?? null) !== true
            || ($manifest['products'] ?? null) !== $expected['products']) {
            throw new RuntimeException('Inventory manifest and current catalogue differ.');
        }
        $db = $orders->connection();

        return $db->transaction(function () use ($db, $manifest) {
            // An initial stock import must never replenish sales or overwrite reservations on a rerun.
            if ($db->table('commerce_inventory')->exists() || $db->table('commerce_orders')->exists()) {
                throw new RuntimeException('Initial inventory is already installed or orders exist.');
            }
            foreach ($manifest['products'] as $row) {
                $db->table('commerce_inventory')->insert([
                    'slug' => $row['slug'], 'reference' => $row['reference'],
                    'quantity' => $row['quantity'], 'reserved' => 0,
                    'approved_price_cents' => $row['approved_price_cents'], 'sale_enabled' => $row['sale_enabled'],
                    'validated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return $manifest['summary'];
        });
    }
}
