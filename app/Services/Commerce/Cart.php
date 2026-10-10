<?php

namespace App\Services\Commerce;

use App\Services\Catalogue;
use Illuminate\Contracts\Session\Session;
use Illuminate\Validation\ValidationException;

final class Cart
{
    public function __construct(private Catalogue $catalogue) {}

    public function eligible(?array $product): bool
    {
        return $product !== null && is_int($product['price_cents'] ?? null)
            && $product['price_cents'] > 0 && $product['price_cents'] <= config('commerce.max_total_cents')
            && ($product['currency'] ?? null) === 'EUR'
            && (! ($product['has_variants'] ?? true) || $this->options($product) !== []);
    }

    public function options(array $product): array
    {
        $options = [];
        foreach ($product['variants'] ?? [] as $index => $variant) {
            if (! is_string($variant['title'] ?? null) || trim($variant['title']) === '') {
                return [];
            }
            // Stable ordinal within the immutable catalogue; never fabricate a manufacturer's SKU.
            $options['v'.($index + 1)] = ['name' => $variant['title'], 'reference' => $variant['sku'] ?? ''];
        }

        return $options;
    }

    public function quantities(Session $session): array
    {
        $items = $session->get('commerce.cart', []);
        if (! is_array($items) || count($items) > config('commerce.max_lines')) {
            throw ValidationException::withMessages(['cart' => __('commerce.invalid_cart')]);
        }
        foreach ($items as $slug => $quantity) {
            if (! is_string($slug) || ! preg_match('/\A[a-z0-9][a-z0-9-]{0,220}(?:~v[1-9][0-9]{0,2})?\z/', $slug)
                || ! is_int($quantity) || $quantity < 1 || $quantity > config('commerce.max_quantity')) {
                throw ValidationException::withMessages(['cart' => __('commerce.invalid_cart')]);
            }
        }
        ksort($items);

        return $items;
    }

    public function count(Session $session): int
    {
        try {
            return array_sum($this->quantities($session));
        } catch (ValidationException) {
            return 0;
        }
    }

    public function put(Session $session, string $slug, int $quantity, bool $add = false, ?string $variant = null): void
    {
        $items = $this->quantities($session);
        [$slug, $storedVariant] = array_pad(explode('~', $slug, 2), 2, null);
        $variant ??= $storedVariant;
        $product = $this->catalogue->product($slug);
        if (($product['has_variants'] ?? false) && ! isset($this->options($product)[$variant ?? ''])) {
            throw ValidationException::withMessages(['variant' => __('commerce.choose_variant')]);
        }
        if (! $this->eligible($product)
            || (! ($product['has_variants'] ?? false) && $variant !== null)) {
            throw ValidationException::withMessages(['cart' => __('commerce.unavailable_product')]);
        }
        $key = $slug.($variant === null ? '' : '~'.$variant);
        $quantity += $add ? ($items[$key] ?? 0) : 0;
        if ($quantity < 1 || $quantity > config('commerce.max_quantity')
            || (! isset($items[$key]) && count($items) >= config('commerce.max_lines'))) {
            throw ValidationException::withMessages(['quantity' => __('commerce.invalid_quantity')]);
        }
        if (config('commerce.inventory_enabled') === true) {
            $available = app(InventoryAvailability::class)->available($product);
            if ($available === null) {
                throw ValidationException::withMessages(['cart' => __('commerce.unavailable_product')]);
            }
            $requested = $quantity;
            foreach ($items as $otherKey => $otherQuantity) {
                if ($otherKey !== $key && explode('~', $otherKey, 2)[0] === $slug) {
                    $requested += $otherQuantity;
                }
            }
            if ($requested > $available) {
                throw ValidationException::withMessages(['quantity' => __('commerce.insufficient_stock')]);
            }
        }
        $items[$key] = $quantity;
        $session->put('commerce.cart', $items);
        $session->forget(['commerce.checkout_key', 'commerce.quote_hash', 'commerce.terms_accepted_at']);
    }

    public function remove(Session $session, string $slug): void
    {
        $items = $this->quantities($session);
        unset($items[$slug]);
        $session->put('commerce.cart', $items);
        $session->forget(['commerce.checkout_key', 'commerce.quote_hash', 'commerce.terms_accepted_at']);
    }

    public function quote(Session $session): array
    {
        $lines = [];
        $total = 0;
        $valid = true;
        foreach ($this->quantities($session) as $key => $quantity) {
            [$slug, $variant] = array_pad(explode('~', $key, 2), 2, null);
            $product = $this->catalogue->product($slug);
            $option = $product ? ($this->options($product)[$variant ?? ''] ?? null) : null;
            $eligible = $this->eligible($product) && (($product['has_variants'] ?? false) ? $option !== null : $variant === null);
            $amount = $eligible ? $product['price_cents'] * $quantity : null;
            $lines[] = ['key' => $key, 'slug' => $slug, 'variant_id' => $variant ?? '', 'variant_name' => $option['name'] ?? '',
                'variant_reference' => $option['reference'] ?? '', 'quantity' => $quantity, 'product' => $product, 'line_cents' => $amount];
            $valid = $valid && $eligible;
            $total += $amount ?? 0;
        }

        $availableBySlug = [];
        $requested = [];
        foreach ($lines as $line) {
            $requested[$line['slug']] = ($requested[$line['slug']] ?? 0) + $line['quantity'];
        }
        foreach ($lines as &$line) {
            if (! array_key_exists($line['slug'], $availableBySlug)) {
                $availableBySlug[$line['slug']] = $line['product'] ? app(InventoryAvailability::class)->available($line['product']) : null;
            }
            $line['available'] = $availableBySlug[$line['slug']];
            $line['stock_problem'] = config('commerce.inventory_enabled') === true
                && ($line['available'] === null || $requested[$line['slug']] > $line['available']);
            $valid = $valid && ! $line['stock_problem'];
        }
        unset($line);

        return ['lines' => $lines, 'subtotal_cents' => $total, 'total_cents' => $total,
            'currency' => 'EUR', 'valid' => $valid && $lines !== [] && $total <= config('commerce.max_total_cents')];
    }
}
