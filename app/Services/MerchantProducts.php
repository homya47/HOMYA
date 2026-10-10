<?php

namespace App\Services;

use App\Services\Commerce\CheckoutReadiness;
use App\Services\Commerce\OrderStore;
use Throwable;
use XMLWriter;

final class MerchantProducts
{
    public function __construct(private Seo $seo, private Catalogue $catalogue) {}

    public function ready(): bool
    {
        $readiness = app(CheckoutReadiness::class);

        return $this->seo->enabled() && config('merchant.enabled') === true
            && config('commerce.inventory_enabled') === true && config('commerce.legal_ready') === true
            && rtrim((string) config('app.url'), '/') === $this->seo->origin()
            && ($readiness->bankReady() || $readiness->cardReady());
    }

    public function reviews(): array
    {
        try {
            $path = config('merchant.reviews_path');
            if (! is_file($path) || filesize($path) > 4_000_000) {
                return [];
            }
            $rows = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);

            return is_array($rows) ? array_filter($rows, 'is_array') : [];
        } catch (Throwable) {
            return [];
        }
    }

    public static function fingerprint(array $product): string
    {
        $facts = [];
        foreach (['slug', 'reference', 'name', 'description', 'brand', 'condition', 'price_cents', 'currency', 'has_variants', 'variants', 'images', 'specifications', 'specification_reviews', 'issues', 'variant_id', 'variant_attributes', 'title_source', 'description_source'] as $key) {
            $facts[$key] = $product[$key] ?? null;
        }

        return hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function validGtin(mixed $gtin): bool
    {
        if (! is_string($gtin) || ! preg_match('/\A(?:\d{8}|\d{12}|\d{13}|\d{14})\z/', $gtin) || (int) $gtin === 0) {
            return false;
        }
        $sum = 0;
        for ($i = strlen($gtin) - 2, $weight = 3; $i >= 0; $i--, $weight = 4 - $weight) {
            $sum += (int) $gtin[$i] * $weight;
        }

        return (10 - $sum % 10) % 10 === (int) substr($gtin, -1);
    }

    public static function condition(array $product): ?string
    {
        // Existing catalogue vocabulary, mapped to Google's accepted English values.
        return ['new' => 'new', 'neuf' => 'new', 'used' => 'used', 'occasion' => 'used',
            'refurbished' => 'refurbished', 'reconditionne' => 'refurbished'][$product['condition'] ?? ''] ?? null;
    }

    public function problems(array $product, array $review, string $locale): array
    {
        $problems = [];
        $fingerprint = is_array($review['fingerprints'] ?? null) ? ($review['fingerprints'][$locale] ?? null) : null;
        if (($review['approved'] ?? false) !== true || ! is_string($fingerprint)
            || ! hash_equals(self::fingerprint($product), $fingerprint)) {
            $problems[] = 'merchant_review_required';
        }
        if (empty($product['name']) || mb_strlen($product['name']) > 150 || empty($product['description']) || mb_strlen($product['description']) > 5000) {
            $problems[] = 'title_or_description_invalid';
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ($product['name'] ?? '').($product['description'] ?? '').($product['brand'] ?? ''))
            || mb_strlen($product['brand'] ?? '') > 70) {
            $problems[] = 'text_format_invalid';
        }
        if (empty($product['images'])) {
            $problems[] = 'image_missing';
        }
        if (! is_int($product['price_cents'] ?? null) || $product['price_cents'] <= 0
            || $product['price_cents'] > config('commerce.max_total_cents') || ($product['currency'] ?? null) !== 'EUR') {
            $problems[] = 'price_or_currency_invalid';
        }
        if (self::condition($product) === null) {
            $problems[] = 'condition_required';
        }
        if ($product['has_variants'] ?? true) {
            $variants = $this->variantProducts($product, $review, $locale);
            if (! $variants) {
                $problems[] = 'variant_data_required';
            }
            $attributeKeys = null;
            $combinations = [];
            foreach ($variants as [$variant, $variantReview]) {
                if ($this->problems($variant, $variantReview, $locale)) {
                    $problems[] = 'variant_review_required';
                }
                $attributes = $variant['variant_attributes'];
                ksort($attributes);
                $keys = array_keys($attributes);
                if ($attributeKeys !== null && $keys !== $attributeKeys) {
                    $problems[] = 'variant_attributes_inconsistent';
                }
                $attributeKeys = $keys;
                $combinations[] = json_encode($attributes, JSON_THROW_ON_ERROR);
            }
            if (count($combinations) !== count(array_unique($combinations))) {
                $problems[] = 'variant_attributes_not_distinct';
            }
        }
        if (isset($product['variant_id']) && empty($product['variant_attributes'])) {
            $problems[] = 'variant_attributes_required';
        }
        if (! empty($product['issues'])) {
            $problems[] = 'catalogue_issues_require_review';
        }
        if (! ($product['has_variants'] ?? false)) {
            $gtin = $review['gtin'] ?? null;
            $mpn = $review['mpn'] ?? null;
            if ($gtin !== null && ! self::validGtin($gtin)) {
                $problems[] = 'gtin_invalid';
            }
            if ($mpn !== null && (! is_string($mpn) || trim($mpn) === '' || mb_strlen($mpn) > 70)) {
                $problems[] = 'mpn_invalid';
            }
            if (($review['identifier_exists'] ?? null) === false) {
                if ($gtin !== null || $mpn !== null) {
                    $problems[] = 'identifiers_contradict_exemption';
                }
            } elseif (! self::validGtin($gtin) && ! (is_string($mpn) && trim($mpn) !== '' && ! empty($product['brand']))) {
                $problems[] = 'manufacturer_identifiers_required';
            }
        }
        // AI provenance must be confirmed, not guessed from the language or source.
        foreach (['title_source', 'description_source'] as $field) {
            if (! in_array($review[$field] ?? null, ['default', 'trained_algorithmic_media'], true)
                || (isset($product[$field]) && ($review[$field] ?? null) !== $product[$field])) {
                $problems[] = 'text_provenance_required';
                break;
            }
        }

        return array_unique($problems);
    }

    /** Shared parent price/inventory (DECISION-061); separate identities, images and factual reviews. */
    public function variantProducts(array $product, array $review, string $locale): array
    {
        $items = [];
        foreach ($product['variants'] ?? [] as $index => $option) {
            if (! is_string($option['title'] ?? null) || trim($option['title']) === '') {
                return [];
            }
            $id = 'v'.($index + 1);
            $variantReview = is_array($review['variants'][$id] ?? null) ? $review['variants'][$id] : [];
            $variant = $product;
            $variant['has_variants'] = false;
            $variant['variant_id'] = $id;
            $variant['variant_title'] = $option['title'];
            $variant['variants'] = [];
            $suffix = ' — '.$option['title'];
            $variant['name'] = rtrim(mb_substr($product['name'], 0, max(1, 150 - mb_strlen($suffix)))).$suffix;
            $variant['title_source'] = 'trained_algorithmic_media';
            $variant['variant_attributes'] = [];
            foreach (['color', 'material', 'pattern', 'size'] as $attribute) {
                $value = $variantReview['attributes'][$locale][$attribute] ?? null;
                if (is_string($value) && trim($value) !== '' && mb_strlen($value) <= 200
                    && ! preg_match('/[\x00-\x1F]/', $value)) {
                    $variant['variant_attributes'][$attribute] = $value;
                }
            }
            // A gallery is not proof that a photo represents this particular finish.
            $variant['images'] = [];
            if (($variantReview['image_verified'] ?? false) === true
                && ($variantReview['option_titles'][$locale] ?? null) === $option['title']) {
                foreach ($variantReview['images'] ?? [] as $url) {
                    foreach ($product['images'] ?? [] as $image) {
                        if ($image['local_url'] === $url && ! in_array($image, $variant['images'], true)) {
                            $variant['images'][] = $image;
                        }
                    }
                }
            }
            $items[] = [$variant, $variantReview];
        }

        return $items;
    }

    public static function id(array $product): string
    {
        return 'homya-'.substr(hash('sha256', $product['slug']), 0, 40).(isset($product['variant_id']) ? '-'.$product['variant_id'] : '');
    }

    public function entries(string $locale): array
    {
        app()->setLocale($locale);
        $summaries = $this->catalogue->products();
        if ($this->catalogue->sourceUnavailable() || $this->catalogue->translationUnavailable() || $summaries->isEmpty()) {
            throw new \RuntimeException('Catalogue unavailable.');
        }
        $reviews = $this->reviews();
        // One read-only inventory query; exporting a feed never expires or writes orders.
        $inventory = app(OrderStore::class)->connection()->table('commerce_inventory')
            ->select(['slug', 'reference', 'sale_enabled', 'approved_price_cents', 'validated_at', 'quantity', 'reserved'])
            ->get()->keyBy('slug');
        $items = [];
        foreach ($summaries as $summary) {
            $slug = $summary['slug'];
            if (($reviews[$slug]['approved'] ?? false) !== true) {
                continue;
            }
            $product = $this->catalogue->product($slug);
            if ($product === null || $this->problems($product, $reviews[$slug], $locale) !== []) {
                continue;
            }
            $stock = $inventory->get($slug);
            if (! $stock || ! $stock->sale_enabled || $stock->validated_at === null
                || $stock->reference !== $product['reference'] || (int) $stock->approved_price_cents !== $product['price_cents']
                || (int) $stock->quantity < (int) $stock->reserved) {
                continue;
            }
            if ($product['has_variants']) {
                foreach ($this->variantProducts($product, $reviews[$slug], $locale) as [$variant, $variantReview]) {
                    $items[] = [$variant, $variantReview, (int) $stock->quantity > (int) $stock->reserved];
                }
            } else {
                $items[] = [$product, $reviews[$slug], (int) $stock->quantity > (int) $stock->reserved];
            }
        }
        if ($this->catalogue->sourceUnavailable() || $this->catalogue->translationUnavailable()) {
            throw new \RuntimeException('Catalogue unavailable.');
        }

        return $items;
    }

    public function xml(array $items, string $locale): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
        $xml->startElement('channel');
        $xml->writeElement('title', 'HOMYA');
        $xml->writeElement('link', $this->seo->url('home', compact('locale')));
        $xml->writeElement('description', 'HOMYA — '.strtoupper($locale));
        foreach ($items as [$product, $review, $inStock]) {
            $xml->startElement('item');
            // Internal feed ID, stable across languages, never a fabricated GTIN/MPN.
            $this->attribute($xml, 'id', self::id($product));
            if (isset($product['variant_id'])) {
                $this->attribute($xml, 'item_group_id', self::id(['slug' => $product['slug']]));
                foreach ($product['variant_attributes'] as $attribute => $value) {
                    $this->attribute($xml, $attribute, $value);
                }
            }
            foreach (['title' => 'name', 'description' => 'description'] as $field => $key) {
                if ($review[$field.'_source'] === 'trained_algorithmic_media') {
                    $xml->startElement('g:structured_'.$field);
                    $this->attribute($xml, 'digital_source_type', 'trained_algorithmic_media');
                    $this->attribute($xml, 'content', $product[$key]);
                    $xml->endElement();
                } else {
                    $this->attribute($xml, $field, $product[$key]);
                }
            }
            $this->attribute($xml, 'link', $this->seo->url('product', ['locale' => $locale, 'slug' => $product['slug']] + (isset($product['variant_id']) ? ['variant' => $product['variant_id']] : [])));
            foreach ($product['images'] as $index => $image) {
                if ($index > 10) {
                    break;
                }
                $this->attribute($xml, $index === 0 ? 'image_link' : 'additional_image_link', $this->seo->origin().$image['local_url']);
            }
            $this->attribute($xml, 'price', number_format($product['price_cents'] / 100, 2, '.', '').' EUR');
            $this->attribute($xml, 'availability', $inStock ? 'in_stock' : 'out_of_stock');
            $this->attribute($xml, 'condition', self::condition($product));
            if (! empty($product['brand'])) {
                $this->attribute($xml, 'brand', $product['brand']);
            }
            foreach (['gtin', 'mpn'] as $identifier) {
                if (isset($review[$identifier])) {
                    $this->attribute($xml, $identifier, $review[$identifier]);
                }
            }
            if (($review['identifier_exists'] ?? null) === false) {
                $this->attribute($xml, 'identifier_exists', 'no');
            }
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function attribute(XMLWriter $xml, string $name, string $value): void
    {
        $xml->writeElement('g:'.$name, $value);
    }
}
