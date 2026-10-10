<?php

namespace App\Services;

use App\Services\Admin\ProductManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class Catalogue
{
    private ?array $archive = null;

    private array $localizedProducts = [];

    private array $translations = [];

    private array $images = [];

    private ?array $mediaOverrides = null;

    private bool $translationFailure = false;

    private bool $sourceFailure = false;

    private CatalogueSource $source;

    public function __construct(?CatalogueSource $source = null)
    {
        $this->source = $source ?? app(CatalogueSource::class);
    }

    public function sourceUnavailable(): bool
    {
        return $this->sourceFailure;
    }

    private function read(callable $operation, mixed $empty): mixed
    {
        if ($this->sourceFailure) {
            return $empty;
        }
        try {
            return $operation();
        } catch (Throwable) {
            // Capture before Laravel reports/debug-renders private PDO/configuration details.
            $this->sourceFailure = true;
            $this->localizedProducts = [];
            $this->translations = [];
            $this->archive = ['meta' => ['complete' => false, 'collected_total' => 0], 'products' => []];
            // One fixed signal per instance; never include the exception or request data.
            try {
                Log::warning('HOMYA_CATALOGUE_SOURCE_UNAVAILABLE');
            } catch (Throwable) {
                // Logging failure must not expose the original source failure.
            }

            return $empty;
        }
    }

    public function archive(): array
    {
        if ($this->archive !== null) {
            return $this->archive;
        }

        return $this->archive = $this->read(fn () => $this->source->archive(), ['meta' => ['complete' => false, 'collected_total' => 0], 'products' => []]);
    }

    public function products(): Collection
    {
        $locale = app()->getLocale();
        if (! isset($this->localizedProducts[$locale])) {
            $products = [];
            $entries = $this->archive()['products'];
            $fallbacks = [];
            foreach ($entries as $entry) {
                if ($this->replacement($entry) === null && $this->image($entry['image'] ?? null) === null) {
                    $fallbacks[] = $entry['slug'];
                }
            }
            $this->read(fn () => $this->source->prefetchProducts($fallbacks), null);
            if ($this->sourceFailure) {
                return collect();
            }
            foreach ($entries as $product) {
                $localized = $this->read(fn () => $this->present($product), null);
                if ($localized === null || $this->sourceFailure) {
                    // Never mix French content into another selected language.
                    $this->localizedProducts[$locale] = [];

                    return collect();
                }
                if (($localized['admin_published'] ?? true) === true) {
                    $products[] = $localized;
                }
            }
            $custom = $this->read(fn () => app(ProductManager::class)->custom(), []);
            $this->localizedProducts[$locale] = $this->sourceFailure ? [] : array_merge($products, $custom);
        }

        return collect($this->localizedProducts[$locale]);
    }

    public function translationUnavailable(): bool
    {
        return $this->translationFailure;
    }

    private function text(string $source): ?string
    {
        $source = preg_replace('/https?:\/\/\S*damdam\S*/i', '', $source);
        $source = trim(preg_replace('/damdam/i', 'HOMYA', $source));
        if (preg_match('/\A\$[a-z0-9]+\z/i', $source)) {
            // Unresolved source payload references are not product prose.
            return '';
        }
        $locale = app()->getLocale();
        if ($locale === 'fr' || $source === '') {
            return $source;
        }
        if (! array_key_exists($locale, $this->translations)) {
            $this->translations[$locale] = $this->read(fn () => $this->source->dictionary($locale), []);
        }
        $translated = $this->translations[$locale][$source] ?? null;
        if (! is_string($translated) || trim($translated) === '') {
            $this->translationFailure = true;

            return null;
        }

        return CatalogueTextCorrections::apply($source, $locale, $translated);
    }

    private function image(?array $image): ?array
    {
        $url = $image['local_url'] ?? '';
        if (! preg_match('/\A\/media\/catalogue\/[a-f0-9]{24}\.(?:jpg|png|webp|avif|gif)\z/', $url) || ! is_file(public_path(ltrim($url, '/')))) {
            return null;
        }

        if (array_key_exists($url, $this->images)) {
            return $this->images[$url];
        }
        $path = public_path(ltrim($url, '/'));
        $dimensions = @getimagesize($path);
        // Prepare for the announced Merchant Center threshold without upscaling.
        // This technical gate does not certify visual quality or Google approval.
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $expectedType = ['jpg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'webp' => IMAGETYPE_WEBP, 'gif' => IMAGETYPE_GIF];
        if (! $dimensions || $dimensions[0] < 500 || $dimensions[1] < 500
            || $dimensions[0] * $dimensions[1] > 64_000_000 || filesize($path) > 16_000_000
            || ($expectedType[$extension] ?? null) !== $dimensions[2]) {
            return $this->images[$url] = null;
        }

        return $this->images[$url] = ['local_url' => $url, 'width' => $dimensions[0], 'height' => $dimensions[1]];
    }

    private function replacement(array $source): ?array
    {
        if ($this->mediaOverrides === null) {
            $this->mediaOverrides = $this->read(fn () => $this->source->mediaOverrides(), []);
        }
        $entry = $this->mediaOverrides[$source['slug']] ?? null;
        // A photo approved for another model must never silently replace this one.
        if (! is_array($entry) || ($entry['reference'] ?? null) !== $source['reference'] || ($entry['visual_review'] ?? null) !== 'verified') {
            return null;
        }

        return is_array($entry['image'] ?? null) ? $this->image($entry['image']) : null;
    }

    private function sourceProduct(string $slug): ?array
    {
        if (! preg_match('/\A[a-z0-9][a-z0-9-]{0,220}\z/', $slug)) {
            return null;
        }

        return $this->read(fn () => $this->source->product($slug), null);
    }

    private function present(array $source): ?array
    {
        // Source attribution/history remains in the private archive, outside public HTML.
        $product = array_intersect_key($source, array_flip(['slug', 'reference', 'universe', 'condition', 'price_cents', 'currency', 'has_variants', 'issues']));
        $quote = $source['currency'] === 'EUR' ? PromotionPricing::quote($source['price_cents']) : null;
        $product['promotion'] = $quote === null ? null : $quote + ['base_price_cents' => $source['price_cents']];
        if ($quote !== null) {
            $product['price_cents'] = $quote['price_cents'];
        }
        $product['brand'] = preg_replace('/damdam/i', 'HOMYA', $source['brand']);
        $product['name'] = $this->text($source['name']);
        $product['category'] = ['slug' => $source['category']['slug'], 'name' => $this->text($source['category']['name'])];
        if ($product['name'] === null || $product['category']['name'] === null) {
            return null;
        }
        $replacement = $this->replacement($source);
        $product['image'] = $replacement ?? $this->image($source['image'] ?? $source['images'][0] ?? null);
        if ($product['image'] === null) {
            // The compact index has no gallery. Read it only when its primary fails.
            $gallerySource = isset($source['images']) ? $source : $this->sourceProduct($source['slug']);
            if (($gallerySource['reference'] ?? null) === $source['reference']) {
                foreach ($gallerySource['images'] ?? [] as $image) {
                    if (($visible = $this->image($image)) !== null) {
                        $product['image'] = $visible;
                        break;
                    }
                }
            }
        }
        if (array_key_exists('description', $source)) {
            $product['images'] = $replacement ? [$replacement] : [];
            $product['description_hidden'] = false;
            foreach ($source['images'] as $image) {
                $visible = $this->image($image);
                if ($visible !== null && ! in_array($visible['local_url'], array_column($product['images'], 'local_url'), true)) {
                    $product['images'][] = $visible;
                }
            }
            if (preg_match('/\A\$[a-z0-9]+\z/i', $source['description'])) {
                $product['issues'][] = 'unresolved_description';
            }
            // Suppression is only a public projection; source prose/files stay intact.
            $product['description'] = $this->text($source['description']);
            if ($product['description'] === null) {
                return null;
            }
            $product['specifications'] = [];
            foreach ($source['specifications'] as $spec) {
                $label = $this->text($spec['label']);
                $value = $this->text($spec['value']);
                if ($label === null || $value === null) {
                    return null;
                }
                $product['specifications'][] = compact('label', 'value');
            }
            $product['variants'] = [];
            foreach ($source['variants'] as $variant) {
                $title = $this->text($variant['title'] ?? '');
                if ($title === null) {
                    return null;
                }
                $product['variants'][] = ['title' => $title] + array_intersect_key($variant, ['sku' => true]);
            }
        }

        $product = CatalogueProductCorrections::apply($source, $product, app()->getLocale());

        return app(ProductManager::class)->decorate(CatalogueContent::apply($source, $product, app()->getLocale()));
    }

    public function product(string $slug): ?array
    {
        if (! preg_match('/\A[a-z0-9][a-z0-9-]{0,220}\z/', $slug)) {
            return null;
        }
        if (! collect($this->archive()['products'])->contains('slug', $slug)) {
            return $this->read(fn () => collect(app(ProductManager::class)->custom())->firstWhere('slug', $slug), null);
        }
        $product = $this->sourceProduct($slug);

        $presented = $product !== null ? $this->read(fn () => $this->present($product), null) : null;

        return $this->sourceFailure || ($presented['admin_published'] ?? true) === false ? null : $presented;
    }

    public function filtered(array $filters): Collection
    {
        $query = Str::ascii(mb_strtolower($filters['q'] ?? ''));
        // Localized refrigerator department links retain the exact French
        // selection; longer phrases and other user queries stay untouched.
        $query = match ($query) {
            'refrigerators', 'kuhlschranke' => 'refrigerateur',
            default => $query,
        };
        $originals = collect($this->archive()['products'])->keyBy('slug');
        $items = $this->products()->filter(function (array $product) use ($filters, $query, $originals) {
            // Retain a user's French search when changing language; display stays localized.
            if ($query !== '') {
                $original = $originals[$product['slug']] ?? $product;
                $searchable = implode(' ', [$product['name'], $product['reference'], $product['brand'], $product['category']['name'], $original['name'], $original['category']['name']]);
                if (! str_contains(Str::ascii(mb_strtolower($searchable)), $query)) {
                    return false;
                }
            }
            foreach (['universe', 'brand', 'condition'] as $key) {
                if (! empty($filters[$key]) && $product[$key] !== $filters[$key]) {
                    return false;
                }
            }
            if (! empty($filters['category']) && $product['category']['slug'] !== $filters['category']) {
                return false;
            }
            foreach (['min', 'max'] as $key) {
                if (isset($filters[$key]) && $filters[$key] !== '') {
                    $threshold = self::cents($filters[$key]);
                    if ($product['price_cents'] === null || ($key === 'min' && $product['price_cents'] < $threshold) || ($key === 'max' && $product['price_cents'] > $threshold)) {
                        return false;
                    }
                }
            }

            return true;
        });
        $sort = $filters['sort'] ?? 'name';
        // Transliteration is linear in the number of retained products, rather
        // than repeated for both names at every sort comparison.
        $names = $items->mapWithKeys(fn (array $product) => [$product['slug'] => Str::ascii($product['name'])]);
        $priceSort = in_array($sort, ['price_asc', 'price_desc'], true);

        return $items->sort(function ($a, $b) use ($sort, $names, $priceSort) {
            if ($priceSort) {
                if (($a['price_cents'] === null) !== ($b['price_cents'] === null)) {
                    return ($a['price_cents'] === null) <=> ($b['price_cents'] === null);
                }
                $comparison = $a['price_cents'] <=> $b['price_cents'];
                if ($comparison !== 0) {
                    return $sort === 'price_desc' ? -$comparison : $comparison;
                }
            }

            return strnatcasecmp($names[$a['slug']], $names[$b['slug']]) ?: strcmp($a['slug'], $b['slug']);
        })->values();
    }

    public static function cents(string $value): int
    {
        $parts = explode('.', str_replace(',', '.', $value));

        return ((int) $parts[0]) * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }

    public static function money(?int $cents, string $locale): string
    {
        if ($cents === null) {
            return __('catalogue.price_missing');
        }
        $decimal = $locale === 'en' ? '.' : ',';
        $group = match ($locale) {
            'en' => ',', 'de' => '.', default => "\u{202f}"
        };

        return number_format(intdiv($cents, 100), 0, '.', $group).$decimal.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT).' €';
    }
}
