<?php

namespace App\Services;

use App\Services\Commerce\CheckoutReadiness;
use Illuminate\Http\Request;

final class Seo
{
    public function origin(): ?string
    {
        $url = rtrim((string) config('seo.public_url'), '/');
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        if (! $parts || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || isset($parts['port']) || ($parts['path'] ?? '') !== ''
            || ! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP)
            || ! filter_var($url, FILTER_VALIDATE_URL) || preg_match('/(?:\.localhost|\.local|\.test|\.invalid|\.example)\z/i', $host)) {
            return null;
        }

        return $url;
    }

    public function enabled(): bool
    {
        return config('seo.indexing_enabled') === true && app()->environment('production')
            && ! config('app.debug') && $this->origin() !== null;
    }

    public function onPublicOrigin(Request $request): bool
    {
        return $this->enabled() && $request->getSchemeAndHttpHost() === $this->origin();
    }

    public function url(string $route, array $parameters): string
    {
        return ($this->origin() ?? request()->getSchemeAndHttpHost()).route($route, $parameters, false);
    }

    public static function text(?string $text, int $limit = 170): string
    {
        // Product prose is displayed as plain text; never interpret embedded HTML.
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text));

        return mb_strlen($text) <= $limit ? $text : rtrim(mb_substr($text, 0, $limit - 1)).'…';
    }

    public function page(Request $request, string $title, string $description, array $context = []): array
    {
        $route = $request->route()?->getName();
        $locale = app()->getLocale();
        $available = ($context['available'] ?? false) === true;
        $public = in_array($route, ['home', 'catalogue', 'promotions', 'product', 'information'], true) && $available;
        $query = [];
        $filtered = false;
        if (in_array($route, ['catalogue', 'promotions'], true)) {
            $filters = $context['filters'] ?? [];
            $allowed = $route === 'catalogue' ? ['category', 'universe', 'page'] : ['page'];
            foreach ($request->query() as $key => $value) {
                if ($value !== '' && $value !== null && ! in_array($key, $allowed, true)
                    && ! str_starts_with($key, 'utm_') && ! in_array($key, ['gclid', 'gbraid', 'wbraid'], true)) {
                    $filtered = true;
                }
            }
            // One category URL regardless of its universe navigation context.
            if ($route === 'catalogue') {
                if (! empty($filters['category'])) {
                    $query['category'] = $filters['category'];
                } elseif (! empty($filters['universe'])) {
                    $query['universe'] = $filters['universe'];
                }
            }
            if (($filters['page'] ?? 1) > 1) {
                $query['page'] = (int) $filters['page'];
                $title .= ' · '.__('catalogue.go_page', ['number' => $query['page']]);
            }
        }
        $parameters = ['locale' => $locale] + $query;
        if ($route === 'product') {
            $parameters['slug'] = $request->route('slug');
        }
        if ($route === 'information') {
            $parameters['page'] = $request->route('page');
            $public = $public && ! ($context['pending'] ?? true)
                && ! in_array($parameters['page'], ['suivi'], true);
        }
        $canonical = $public && ! $filtered ? $this->url($route, $parameters) : null;
        $indexable = $canonical !== null && $this->onPublicOrigin($request);
        $robots = $indexable ? 'index, follow, max-image-preview:large' : ($this->onPublicOrigin($request) && $public ? 'noindex, follow' : 'noindex, nofollow');
        $request->attributes->set('seo.robots', $robots);
        $alternates = [];
        if ($canonical !== null) {
            foreach (config('homya.locales') as $language) {
                $alternates[$language] = $this->url($route, array_replace($parameters, ['locale' => $language]) + ($route === 'product' && ! empty($context['variant']) ? ['variant' => $context['variant']] : []));
            }
            $alternates['x-default'] = $alternates['fr'];
        }
        $graph = [];
        $image = null;
        if ($canonical !== null) {
            $graph[] = ['@type' => 'WebSite', '@id' => $this->url('home', ['locale' => 'fr']).'#website', 'url' => $this->url('home', ['locale' => 'fr']), 'name' => 'HOMYA'];
            if ($route !== 'home') {
                $breadcrumbs = [['@type' => 'ListItem', 'position' => 1, 'name' => __('home.home'), 'item' => $this->url('home', ['locale' => $locale])]];
                if ($route === 'product') {
                    $breadcrumbs[] = ['@type' => 'ListItem', 'position' => 2, 'name' => __('catalogue.title'), 'item' => $this->url('catalogue', ['locale' => $locale])];
                    $category = $context['product']['category'] ?? null;
                    if ($category) {
                        $breadcrumbs[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $category['name'], 'item' => $this->url('catalogue', ['locale' => $locale, 'category' => $category['slug']])];
                    }
                }
                $breadcrumbs[] = ['@type' => 'ListItem', 'position' => count($breadcrumbs) + 1, 'name' => $context['name'] ?? $title, 'item' => $canonical];
                $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $breadcrumbs];
            }
            if ($route === 'product' && isset($context['product'])) {
                $product = $context['product'];
                $node = ['@type' => 'Product', '@id' => $canonical.'#product', 'url' => $canonical, 'name' => $product['name']];
                if (! empty($product['description'])) {
                    $node['description'] = $product['description'];
                }
                if (! empty($product['reference'])) {
                    $node['sku'] = $product['reference'];
                }
                if (! empty($product['brand'])) {
                    $node['brand'] = ['@type' => 'Brand', 'name' => $product['brand']];
                }
                $images = array_column($product['images'] ?? [], 'local_url');
                if ($images) {
                    $node['image'] = array_map(fn ($path) => ($this->origin() ?? $request->getSchemeAndHttpHost()).$path, $images);
                    $image = $node['image'][0];
                }
                // Deferred orders and unselected variants are not an actionable offer.
                $readiness = app(CheckoutReadiness::class);
                if ($indexable && ($readiness->bankReady() || $readiness->cardReady())
                    && ! ($product['has_variants'] ?? true) && is_int($product['price_cents'] ?? null)
                    && $product['price_cents'] > 0 && $product['price_cents'] <= config('commerce.max_total_cents')
                    && empty($product['issues']) && ($product['currency'] ?? null) === 'EUR'
                    && ($context['available_stock'] ?? null) !== null && $images) {
                    $node['offers'] = ['@type' => 'Offer', 'url' => $canonical,
                        'price' => number_format($product['price_cents'] / 100, 2, '.', ''), 'priceCurrency' => 'EUR',
                        'availability' => 'https://schema.org/'.($context['available_stock'] > 0 ? 'InStock' : 'OutOfStock')];
                    if ($condition = ['new' => 'NewCondition', 'used' => 'UsedCondition', 'refurbished' => 'RefurbishedCondition'][MerchantProducts::condition($product) ?? ''] ?? null) {
                        $node['offers']['itemCondition'] = 'https://schema.org/'.$condition;
                    }
                }
                // Publish variant properties/offers only after their separate factual and image reviews.
                if ($product['has_variants'] ?? false) {
                    $merchant = app(MerchantProducts::class);
                    $review = $merchant->reviews()[$product['slug']] ?? [];
                    if ($merchant->problems($product, $review, $locale) === []) {
                        $node['@type'] = 'ProductGroup';
                        $node['productGroupID'] = MerchantProducts::id($product);
                        $node['hasVariant'] = [];
                        foreach ($merchant->variantProducts($product, $review, $locale) as [$variant, $variantReview]) {
                            $variantUrl = $this->url('product', ['locale' => $locale, 'slug' => $product['slug'], 'variant' => $variant['variant_id']]);
                            $child = ['@type' => 'Product', '@id' => $variantUrl.'#product', 'url' => $variantUrl,
                                'name' => $variant['name'], 'description' => $variant['description'], 'sku' => MerchantProducts::id($variant),
                                'image' => array_map(fn ($entry) => $this->origin().$entry['local_url'], $variant['images'])] + $variant['variant_attributes'];
                            foreach (['gtin', 'mpn'] as $identifier) {
                                if (isset($variantReview[$identifier])) {
                                    $child[$identifier] = $variantReview[$identifier];
                                }
                            }
                            if ($indexable && ($readiness->bankReady() || $readiness->cardReady()) && ($context['available_stock'] ?? null) !== null) {
                                $child['offers'] = ['@type' => 'Offer', 'url' => $variantUrl, 'priceCurrency' => 'EUR',
                                    'price' => number_format($variant['price_cents'] / 100, 2, '.', ''),
                                    'availability' => 'https://schema.org/'.($context['available_stock'] > 0 ? 'InStock' : 'OutOfStock')];
                                $child['offers']['itemCondition'] = 'https://schema.org/'.(['new' => 'NewCondition', 'used' => 'UsedCondition', 'refurbished' => 'RefurbishedCondition'][MerchantProducts::condition($variant)]);
                            }
                            $node['hasVariant'][] = $child;
                            $node['variesBy'] = array_map(fn ($key) => 'https://schema.org/'.$key, array_keys($variant['variant_attributes']));
                        }
                    }
                }
                $graph[] = $node;
            }
        }

        return ['title' => self::text($title, 240), 'description' => self::text($description),
            'robots' => $robots, 'canonical' => $canonical, 'alternates' => $alternates, 'image' => $image,
            'type' => $route === 'product' ? 'product' : 'website',
            'json' => $graph ? json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null];
    }
}
