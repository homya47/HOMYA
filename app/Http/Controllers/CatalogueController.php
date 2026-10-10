<?php

namespace App\Http\Controllers;

use App\Services\Catalogue;
use App\Services\Commerce\Cart;
use App\Services\MerchantProducts;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CatalogueController extends Controller
{
    public function index(Request $request, Catalogue $catalogue, string $locale)
    {
        app()->setLocale($locale);
        $all = $catalogue->products();
        if ($catalogue->sourceUnavailable()) {
            return $this->unavailable($locale);
        }
        if ($catalogue->translationUnavailable()) {
            return $this->unavailable($locale, translationFailure: true);
        }
        $universes = ['firewood', 'heating', 'appliances', 'furniture', 'televisions', 'other'];
        $categoryOptions = $all->filter(fn (array $product) => ! empty($product['category']['slug']))
            ->groupBy('category.slug')->map(function ($entries) {
                return $entries->first()['category'] + ['universes' => $entries->pluck('universe')->filter()->unique()->sort()->values()->all()];
            })->sortBy('name')->values();
        $universeValue = $request->query('universe');
        $categoryUniverse = is_string($universeValue) && in_array($universeValue, $universes, true) ? $universeValue : null;
        $categories = $categoryOptions->filter(fn (array $category) => $categoryUniverse === null || in_array($categoryUniverse, $category['universes'], true))
            ->map(fn (array $category) => ['slug' => $category['slug'], 'name' => $category['name']])->values();
        $categoryReset = $request->query('category_reset') === '1' && $categoryUniverse !== null;
        $brands = $all->pluck('brand')->filter()->unique()->sort()->values();
        $conditions = $all->pluck('condition')->filter()->unique()->sort()->values();
        $validator = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:120'],
            'universe' => ['nullable', Rule::in($universes)],
            'category' => ['nullable', Rule::in($categoryOptions->pluck('slug')->all())],
            'brand' => ['nullable', Rule::in($brands->all())],
            'condition' => ['nullable', Rule::in($all->pluck('condition')->unique()->all())],
            'min' => ['nullable', 'string', 'regex:/\A\d{1,8}([.,]\d{1,2})?\z/'],
            'max' => ['nullable', 'string', 'regex:/\A\d{1,8}([.,]\d{1,2})?\z/'],
            'sort' => ['nullable', Rule::in(['name', 'price_asc', 'price_desc'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'category_reset' => ['nullable', 'string', Rule::in(['1'])],
        ]);
        $invalid = $validator->fails();
        $errorFields = array_keys($validator->errors()->messages());
        $formValues = $this->boundedValues($request);
        $filters = $invalid ? [] : array_filter(array_diff_key($validator->validated(), ['category_reset' => true]), fn ($v) => $v !== null && $v !== '');
        if (! $invalid && isset($filters['min'], $filters['max']) && Catalogue::cents($filters['min']) > Catalogue::cents($filters['max'])) {
            $invalid = true;
            $errorFields = ['min', 'max'];
            $filters = [];
        }
        if (! $invalid && isset($filters['universe'], $filters['category'])
            && ! in_array($filters['universe'], $categoryOptions->firstWhere('slug', $filters['category'])['universes'], true)) {
            $normalized = array_diff_key($filters, ['category' => true, 'page' => true]);

            return redirect()->route('catalogue', ['locale' => $locale] + $normalized + ['category_reset' => '1']);
        }
        $activeFilters = [];
        if (! $invalid) {
            foreach (array_diff_key($filters, ['page' => true, 'sort' => true]) as $key => $value) {
                $label = match ($key) {
                    'q' => __('home.search'),
                    'universe' => $value === 'other' ? __('catalogue.other') : __('home.categories.'.$value.'.name'),
                    'category' => $categories->firstWhere('slug', $value)['name'],
                    'condition' => __('catalogue.conditions')[$value] ?? $value,
                    default => __('catalogue.'.$key),
                };
                $display = in_array($key, ['universe', 'category', 'condition'], true) ? $label : $label.': '.$value;
                $remaining = array_diff_key($filters, [$key => true, 'page' => true]);
                $activeFilters[] = ['text' => $display, 'url' => route('catalogue', ['locale' => $locale] + $remaining).'#results-heading'];
            }
        }
        $items = $catalogue->filtered($filters);
        $page = (int) ($filters['page'] ?? 1);
        $last = max(1, (int) ceil($items->count() / 24));
        if (! $invalid && $page > $last) {
            return redirect()->route('catalogue', ['locale' => $locale] + array_merge($filters, ['page' => $last]));
        }
        $products = new LengthAwarePaginator($items->forPage($page, 24)->values(), $items->count(), 24, $page, ['path' => route('catalogue', ['locale' => $locale]), 'query' => $filters]);
        $languageLinks = [];
        foreach (config('homya.locales') as $language) {
            $languageLinks[$language] = route('catalogue', ['locale' => $language] + ($invalid ? $formValues : $filters));
        }
        $selectedUniverse = $invalid ? null : ($filters['universe'] ?? null);
        $universeProducts = $selectedUniverse ? $all->where('universe', $selectedUniverse) : $all;
        $catalogueIntro = [
            'title' => $selectedUniverse
                ? ($selectedUniverse === 'other' ? __('catalogue.other') : __('home.categories.'.$selectedUniverse.'.name'))
                : __('catalogue.title'),
            'description' => $selectedUniverse && $selectedUniverse !== 'other'
                ? __('home.categories.'.$selectedUniverse.'.description') : __('catalogue.description'),
            'count' => $universeProducts->count(),
            'universe' => $selectedUniverse,
            'image' => null,
        ];
        if (! $invalid && isset($filters['category'])) {
            $catalogueIntro['title'] = $categoryOptions->firstWhere('slug', $filters['category'])['name'];
            $catalogueIntro['description'] = __('seo.category_description', ['category' => $catalogueIntro['title']]);
        }
        if ($selectedUniverse) {
            $department = collect(config('home.departments', []))->first(fn (array $entry) => ($entry['filters']['universe'] ?? null) === $selectedUniverse);
            if ($department && is_file(public_path('media/home/'.$department['image']))) {
                $catalogueIntro['image'] = ['local_url' => '/media/home/'.$department['image'], 'width' => $department['width'], 'height' => $department['height']];
            } else {
                $catalogueIntro['image'] = $universeProducts->first(fn (array $entry) => $entry['image'] !== null)['image'] ?? null;
            }
        }
        $browseCategories = $invalid ? collect() : $universeProducts->groupBy('category.slug')->map(function ($entries) use ($locale, $selectedUniverse) {
            $category = $entries->first()['category'];

            return $category + ['count' => $entries->count(), 'url' => route('catalogue', ['locale' => $locale, 'category' => $category['slug']] + ($selectedUniverse ? ['universe' => $selectedUniverse] : []))];
        })->sortBy('name')->take(8)->values();

        return response()->view('catalogue.index', compact('products', 'categories', 'categoryOptions', 'categoryReset', 'brands', 'conditions', 'filters', 'formValues', 'errorFields', 'activeFilters', 'languageLinks', 'invalid', 'locale', 'catalogueIntro', 'browseCategories') + ['meta' => $catalogue->archive()['meta'], 'catalogueReady' => $all->isNotEmpty()], $invalid ? 422 : ($all->isEmpty() ? 503 : 200));
    }

    public function show(Request $request, Catalogue $catalogue, string $locale, string $slug)
    {
        app()->setLocale($locale);
        // Navigation context is bounded and internal; never accept a return URL.
        $context = $this->navigationContext($request);
        $languageLinks = [];
        foreach (config('homya.locales') as $language) {
            $languageLinks[$language] = route('product', ['locale' => $language, 'slug' => $slug] + $context);
        }
        $product = $catalogue->product($slug);
        if ($catalogue->sourceUnavailable()) {
            return $this->unavailable($locale, $slug);
        }
        if (! $product) {
            if ($catalogue->translationUnavailable()) {
                return response()->view('catalogue.unavailable', compact('locale', 'slug', 'languageLinks') + ['catalogueReady' => false, 'translationFailure' => true], 503);
            }

            $ready = $catalogue->products()->isNotEmpty();
            if ($catalogue->sourceUnavailable()) {
                return $this->unavailable($locale, $slug);
            }

            return response()->view('catalogue.not-found', ['languageLinks' => $languageLinks, 'catalogueReady' => $ready], 404);
        }

        $selectedVariant = $request->query('variant');
        $variantOptions = app(Cart::class)->options($product);
        if (array_key_exists('variant', $request->query()) && (! is_string($selectedVariant) || ! ($product['has_variants'] ?? false) || ! isset($variantOptions[$selectedVariant]))) {
            abort(404);
        }
        if ($selectedVariant !== null) {
            foreach (config('homya.locales') as $language) {
                $languageLinks[$language] = route('product', ['locale' => $language, 'slug' => $slug, 'variant' => $selectedVariant] + $context);
            }
        }

        $catalogueLink = route('catalogue', ['locale' => $locale] + ($context ?: ['category' => $product['category']['slug']])).'#results-heading';
        $all = $catalogue->products();
        if ($catalogue->sourceUnavailable()) {
            return $this->unavailable($locale, $slug);
        }
        $relatedUnavailable = $catalogue->translationUnavailable();
        $relatedProducts = $relatedUnavailable ? collect() : $all->filter(fn (array $entry) => $entry['slug'] !== $slug && $entry['category']['slug'] === $product['category']['slug'])
            ->sortBy('name')->take(4)->values();

        $seoProduct = $product;
        $variantImageVerified = false;
        if ($selectedVariant !== null) {
            $merchant = app(MerchantProducts::class);
            foreach ($merchant->variantProducts($product, $merchant->reviews()[$slug] ?? [], $locale) as [$variant]) {
                if ($variant['variant_id'] === $selectedVariant) {
                    $product['name'] = $variant['name'];
                    if ($variant['images']) {
                        $product['images'] = $variant['images'];
                        $variantImageVerified = true;
                    }
                }
            }
        }

        return view('catalogue.product', compact('product', 'seoProduct', 'languageLinks', 'locale', 'context', 'catalogueLink', 'relatedProducts', 'relatedUnavailable', 'selectedVariant', 'variantOptions', 'variantImageVerified') + ['catalogueReady' => true]);
    }

    private function boundedValues(Request $request): array
    {
        $values = [];
        foreach (['q' => 120, 'universe' => 24, 'category' => 160, 'brand' => 200, 'condition' => 40, 'min' => 11, 'max' => 11, 'sort' => 20, 'page' => 6] as $key => $limit) {
            $value = $request->query($key);
            if (is_string($value) && $value !== '') {
                $values[$key] = mb_substr($value, 0, $limit);
            }
        }

        return $values;
    }

    private function navigationContext(Request $request): array
    {
        $rules = [
            'q' => ['string', 'max:120'],
            'universe' => [Rule::in(['firewood', 'heating', 'appliances', 'furniture', 'televisions', 'other'])],
            'category' => ['regex:/\A[a-z0-9][a-z0-9-]{0,159}\z/'],
            'brand' => ['string', 'max:200'],
            'condition' => ['string', 'max:40'],
            'min' => ['regex:/\A\d{1,8}([.,]\d{1,2})?\z/'],
            'max' => ['regex:/\A\d{1,8}([.,]\d{1,2})?\z/'],
            'sort' => [Rule::in(['name', 'price_asc', 'price_desc'])],
            'page' => ['integer', 'min:1', 'max:100000'],
        ];
        $context = [];
        foreach ($this->boundedValues($request) as $key => $value) {
            if (Validator::make([$key => $value], [$key => $rules[$key]])->passes()) {
                $context[$key] = $value;
            }
        }
        if (isset($context['min'], $context['max']) && Catalogue::cents($context['min']) > Catalogue::cents($context['max'])) {
            unset($context['min'], $context['max']);
        }

        return $context;
    }

    private function unavailable(string $locale, ?string $slug = null, bool $translationFailure = false)
    {
        $languageLinks = [];
        foreach (config('homya.locales') as $language) {
            $languageLinks[$language] = $slug === null
                ? route('catalogue', ['locale' => $language])
                : route('product', ['locale' => $language, 'slug' => $slug]);
        }

        return response()->view('catalogue.unavailable', compact('locale', 'slug', 'languageLinks')
            + ['catalogueReady' => false, 'translationFailure' => $translationFailure], 503);
    }
}
