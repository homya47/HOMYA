<?php

namespace App\Http\Controllers;

use App\Services\Admin\Activity;
use App\Services\Commerce\Cart;
use App\Services\Commerce\CheckoutReadiness;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CartController extends CommerceController
{
    public function show(Request $request, Cart $cart, CheckoutReadiness $readiness, string $locale)
    {
        app()->setLocale($locale);
        $quote = $cart->quote($request->session());

        return view('commerce.cart', compact('quote', 'locale') + ['checkoutReady' => $readiness->ready(), 'bankReady' => $readiness->bankReady(),
            'languageLinks' => collect(config('homya.locales'))->mapWithKeys(fn ($language) => [$language => route('cart.show', ['locale' => $language])])->all()]);
    }

    public function add(Request $request, Cart $cart, CheckoutReadiness $readiness, string $locale)
    {
        app()->setLocale($locale);
        $data = $request->validate(['slug' => ['required', 'string', 'regex:/\A[a-z0-9][a-z0-9-]{0,220}\z/'],
            'variant' => ['nullable', 'string', 'regex:/\Av[1-9][0-9]{0,2}\z/'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.config('commerce.max_quantity')],
            'intent' => ['nullable', Rule::in(['cart', 'checkout'])]]);
        $cart->put($request->session(), $data['slug'], (int) $data['quantity'], true, $data['variant'] ?? null);
        try {
            app(Activity::class)->aggregate('cart_add', $data['slug']);
        } catch (\Throwable) {
        }
        $quote = $cart->quote($request->session());
        $line = collect($quote['lines'])->firstWhere('key', $data['slug'].(empty($data['variant']) ? '' : '~'.$data['variant']));
        $message = __('commerce.added_detail', ['name' => $line['product']['name'], 'count' => $data['quantity']]);
        if (($data['intent'] ?? 'cart') === 'checkout' && $readiness->ready()) {
            return redirect()->route('checkout.show', ['locale' => $locale])->with('commerce_message', $message);
        }

        return redirect()->route('cart.show', ['locale' => $locale])->with('commerce_message', $message);
    }

    public function update(Request $request, Cart $cart, string $locale, string $slug)
    {
        app()->setLocale($locale);
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:'.config('commerce.max_quantity')]]);
        $cart->put($request->session(), $slug, (int) $data['quantity']);

        return redirect()->route('cart.show', ['locale' => $locale])->with('commerce_message', __('commerce.updated'));
    }

    public function remove(Request $request, Cart $cart, string $locale, string $slug)
    {
        app()->setLocale($locale);
        $cart->remove($request->session(), $slug);

        return redirect()->route('cart.show', ['locale' => $locale])->with('commerce_message', __('commerce.removed'));
    }
}
