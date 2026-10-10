@props(['product', 'locale', 'filters' => [], 'headingLevel' => 'h2'])
<article class="product-card">
    <a href="{{ route('product', ['locale' => $locale, 'slug' => $product['slug']] + $filters) }}" class="product-card-link">
        <div class="product-card-visual">
            <x-product-image :src="$product['image']['local_url'] ?? null" :width="$product['image']['width'] ?? null" :height="$product['image']['height'] ?? null" alt="" />
            @if($product['promotion'] ?? null)<span class="promotion-badge">−{{ $product['promotion']['percent'] }} %</span>@endif
        </div>
        <div class="product-card-copy">
            <p class="product-brand">{{ $product['brand'] ?: __('catalogue.brand_missing') }}</p>
            <{{ $headingLevel === 'h3' ? 'h3' : 'h2' }}>{{ $product['name'] }}</{{ $headingLevel === 'h3' ? 'h3' : 'h2' }}>
            @if($product['reference'])<p class="product-reference">{{ __('catalogue.reference') }} {{ $product['reference'] }}</p>@endif
            <x-product-price :product="$product" :locale="$locale" />
            @if($product['price_cents'] !== null)<p class="product-tax-label">{{ __('commerce.tax_included') }}</p>@endif
            <span class="product-card-action">{{ __('catalogue.view_product') }} <span aria-hidden="true">↗</span></span>
        </div>
    </a>
    <div class="product-card-purchase">
        <p>{{ __('commerce.shipping_free') }}</p>
        <p><a href="{{ route('product', ['locale' => $locale, 'slug' => $product['slug']] + $filters) }}#product-purchase-information">{{ __('commerce.free_returns') }}<span class="sr-only"> — {{ $product['name'] }}</span></a></p>
        <x-purchase-information :locale="$locale" />
        @if(app(\App\Services\Commerce\Cart::class)->eligible($product))
            @if($product['has_variants'])
                <a class="button-secondary" href="{{ route('product', ['locale' => $locale, 'slug' => $product['slug']] + $filters) }}#product-purchase">{{ __('commerce.choose_options') }}<span class="sr-only"> — {{ $product['name'] }}</span></a>
            @else
                <form method="post" action="{{ route('cart.add', ['locale' => $locale]) }}">@csrf
                    <input type="hidden" name="slug" value="{{ $product['slug'] }}"><input type="hidden" name="quantity" value="1">
                    <x-add-to-cart-button :product-name="$product['name']" />
                </form>
            @endif
        @else<p>{{ __('commerce.unavailable_product') }}</p>@endif
    </div>
</article>
