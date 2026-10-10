<?php

namespace App\Http\Controllers;

use App\Services\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PromotionsController extends Controller
{
    public function __invoke(Request $request, Catalogue $catalogue, string $locale)
    {
        app()->setLocale($locale);
        $validator = Validator::make($request->query(), [
            'q' => ['nullable', 'string', 'max:120'],
            'discount' => ['nullable', 'string', Rule::in(['5', '15', '25'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);
        $invalid = $validator->fails();
        $queryInvalid = $validator->errors()->has('q');
        $filters = $invalid ? [] : array_filter($validator->validated(), static fn ($value) => $value !== null && $value !== '');
        $languageLinks = [];
        foreach (config('homya.locales') as $language) {
            $languageLinks[$language] = route('promotions', ['locale' => $language] + $filters);
        }
        $all = $catalogue->products();
        if ($catalogue->sourceUnavailable() || $catalogue->translationUnavailable()) {
            return response()->view('catalogue.unavailable', compact('locale', 'languageLinks') + [
                'catalogueReady' => false, 'translationFailure' => $catalogue->translationUnavailable(), 'slug' => null,
            ], 503);
        }
        $items = $invalid ? collect() : $catalogue->filtered(['q' => $filters['q'] ?? ''])
            ->filter(static fn (array $product) => $product['promotion'] !== null
                && (! isset($filters['discount']) || $product['promotion']['percent'] === (int) $filters['discount']))
            ->sort(static fn (array $a, array $b) => ($b['promotion']['price_cents'] <=> $a['promotion']['price_cents']) ?: strcmp($a['slug'], $b['slug']))
            ->values();
        $page = (int) ($filters['page'] ?? 1);
        $last = max(1, (int) ceil($items->count() / 24));
        if (! $invalid && $page > $last) {
            return redirect()->route('promotions', ['locale' => $locale] + array_merge($filters, ['page' => $last]));
        }
        $products = new LengthAwarePaginator($items->forPage($page, 24)->values(), $items->count(), 24, $page, [
            'path' => route('promotions', ['locale' => $locale]), 'query' => $filters,
        ]);

        return response()->view('promotions', compact('products', 'filters', 'languageLinks', 'invalid', 'queryInvalid', 'locale') + [
            'catalogueReady' => $all->isNotEmpty(),
        ], $invalid ? 422 : ($all->isEmpty() ? 503 : 200));
    }
}
