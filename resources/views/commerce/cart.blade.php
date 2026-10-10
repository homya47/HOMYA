<x-layout :title="__('commerce.cart').' — HOMYA'" :language-links="$languageLinks" :catalogue-ready="true">
    <main id="main" class="shell commerce-page cart-page" tabindex="-1">
        <h1>{{ __('commerce.cart') }}</h1>
        <x-checkout-steps current="cart" :locale="$locale" />
        <x-commerce-errors />
        @if(session('commerce_message'))<p class="commerce-success" role="status" tabindex="-1">{{ session('commerce_message') }}</p>@endif
        @if(!$quote['lines'])
            <section class="cart-empty"><p>{{ __('commerce.empty') }}</p><p class="checkout-help">{{ __('commerce.empty_cart_help') }}</p><a class="button-primary" href="{{ route('catalogue', ['locale' => $locale]) }}">{{ __('commerce.continue') }}</a></section>
        @else
            <div class="commerce-grid cart-grid">
                <div class="cart-main">
                <section class="cart-items" aria-label="{{ __('commerce.items') }}">
                    @foreach($quote['lines'] as $line)<article class="cart-line" aria-labelledby="cart-product-{{ $loop->index }}">
                        @if($line['product'])<x-product-image :src="$line['product']['image']['local_url'] ?? null" :alt="$line['product']['name']" sizes="100px" />@endif
                        <div class="cart-product-copy"><h2 id="cart-product-{{ $loop->index }}">@if($line['product'])<a href="{{ route('product', ['locale' => $locale, 'slug' => $line['slug']]) }}">{{ $line['product']['name'] }}</a>@else{{ __('commerce.unavailable_product') }}@endif</h2>
                            @if($line['variant_name'])<p class="cart-product-meta">{{ $line['variant_name'] }}</p>@endif
                            @if($line['product'])<p class="cart-product-meta">{{ __('catalogue.reference') }} {{ $line['product']['reference'] }}</p>@endif
                            @if($line['stock_problem'])<p class="checkout-field-error" role="alert">{{ $line['available'] === null ? __('commerce.unavailable_product') : __('commerce.insufficient_stock') }}</p>@endif
                            <div class="cart-item-controls">
                                <form method="post" action="{{ route('cart.update', ['locale' => $locale, 'slug' => $line['key']]) }}" class="quantity-form" data-cart-quantity>@csrf @method('PATCH')
                                    <x-quantity-selector :id="'quantity-'.$loop->index" :value="$line['quantity']" :max="config('commerce.max_quantity')" />
                                    <button class="button-secondary cart-update" type="submit" data-busy-label="{{ __('commerce.updating') }}"><span data-submit-label>{{ __('commerce.update') }}</span><span class="sr-only"> — {{ $line['product']['name'] ?? __('commerce.unavailable_product') }}</span></button>
                                </form>
                                <form method="post" action="{{ route('cart.remove', ['locale' => $locale, 'slug' => $line['key']]) }}" data-cart-remove>@csrf @method('DELETE')
                                    <button class="commerce-link cart-remove" type="submit" data-busy-label="{{ __('commerce.removing') }}"><span data-submit-label>{{ __('commerce.remove') }}</span><span class="sr-only"> — {{ $line['product']['name'] ?? __('commerce.unavailable_product') }}</span></button>
                                </form>
                            </div>
                        </div>
                        <div class="cart-prices"><strong>{{ $line['line_cents'] === null ? __('commerce.unavailable_product') : \App\Services\Catalogue::money($line['line_cents'], $locale) }}</strong>
                            @if($line['line_cents'] !== null)<span>{{ __('commerce.unit_price') }} : {{ \App\Services\Catalogue::money($line['product']['price_cents'], $locale) }}</span>@endif
                        </div>
                        @if($line['product'])<x-purchase-information :locale="$locale" :product-name="$line['product']['name']" />@endif
                    </article>@endforeach
                </section>
                    @if($checkoutReady && !$bankReady)<section class="checkout-panel"><h2>{{ __('commerce.status.pending_payment_details') }}</h2><p>{{ __('commerce.deferred_notice') }}</p></section>@else<x-bank-transfer-guide :locale="$locale" :ready="$bankReady" />@endif
                </div>
                <aside class="commerce-summary cart-summary" aria-labelledby="cart-summary-heading"><h2 id="cart-summary-heading">{{ __('commerce.summary') }}</h2>
                    @if($quote['valid'])<dl class="checkout-totals"><div><dt>{{ __('commerce.subtotal') }}</dt><dd>{{ \App\Services\Catalogue::money($quote['subtotal_cents'], $locale) }}</dd></div><div><dt>{{ __('commerce.delivery') }}</dt><dd class="cart-free-shipping">{{ __('commerce.free_delivery') }}</dd></div><div class="checkout-total"><dt>{{ __('commerce.total_tax_included') }}</dt><dd>{{ \App\Services\Catalogue::money($quote['total_cents'], $locale) }}</dd></div></dl>@endif
                    <p class="checkout-help">{{ __('commerce.shipping_free') }} {{ __('commerce.delivery_estimate') }}</p>
                    @if(!$quote['valid'])<p id="cart-checkout-status" class="checkout-field-error">{{ __('commerce.review_cart') }}</p>@elseif(!$checkoutReady)<p id="cart-checkout-status" class="checkout-help">{{ __('commerce.sales_unavailable') }}</p>@endif
                    @if($checkoutReady && $quote['valid'])<a class="button-primary cart-checkout" href="{{ route('checkout.show', ['locale' => $locale]) }}">{{ __('commerce.proceed_checkout') }} <span aria-hidden="true">→</span></a>@else<button class="button-primary cart-checkout" type="button" disabled aria-describedby="cart-checkout-status">{{ __('commerce.proceed_checkout') }}</button>@endif
                    <a class="cart-continue" href="{{ route('catalogue', ['locale' => $locale]) }}">{{ __('commerce.continue') }}</a>
                    <p class="checkout-help">{{ __('commerce.checkout_cart_help') }}</p>
                    <p class="checkout-return"><a href="{{ route('information', ['locale' => $locale, 'page' => 'retractation']) }}">{{ __('commerce.free_returns') }}</a></p>
                    <p class="checkout-help">{{ __('commerce.cart_reservation_notice') }}</p>
                    @if(session('commerce.last_order'))<p><a href="{{ route('order.show', ['locale' => $locale, 'id' => session('commerce.last_order')]) }}">{{ __('commerce.last_order') }}</a></p>@endif
                </aside>
            </div>
            <x-purchase-help :locale="$locale" />
        @endif
    </main>
</x-layout>
