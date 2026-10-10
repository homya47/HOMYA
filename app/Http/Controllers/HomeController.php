<?php

namespace App\Http\Controllers;

use App\Services\Catalogue;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(string $locale, Catalogue $catalogue): View
    {
        app()->setLocale($locale);

        // Share the catalogue's safe, localized projection; never fetch a second
        // selection with different prices, images or provenance rules.
        $products = $catalogue->products();
        $catalogueUnavailable = $catalogue->sourceUnavailable() || $catalogue->translationUnavailable();
        $bySlug = $products->keyBy('slug');
        $selection = static fn (string $name) => collect(config('home.'.$name, []))
            ->map(fn (string $slug) => $bySlug->get($slug))
            ->filter()->values();
        $universeImages = [];
        foreach (['firewood', 'heating', 'appliances', 'furniture', 'televisions'] as $universe) {
            $universeImages[$universe] = $products->first(fn (array $product) => $product['universe'] === $universe && $product['image'] !== null)['image'] ?? null;
        }

        return view('home', [
            'locale' => $locale,
            'catalogueReady' => ! $catalogueUnavailable && $products->isNotEmpty(),
            'catalogueUnavailable' => $catalogueUnavailable,
            'departments' => config('home.departments', []),
            'featuredProducts' => $selection('featured'),
            'discoveryProducts' => $selection('discovery'),
            'universeImages' => $universeImages,
        ]);
    }
}
