@props(['product', 'locale'])
<p class="product-price @if($product['promotion'] ?? null) promotion-price @endif">
    <span class="product-current-price">@if($product['promotion'] ?? null)<span class="sr-only">{{ __('promotions.reduced_price') }} </span>@endif{{ \App\Services\Catalogue::money($product['price_cents'], $locale) }}</span>
    @if($product['promotion'] ?? null)
        <span class="promotion-original-price"><span class="sr-only">{{ __('promotions.original_price') }} </span><s>{{ \App\Services\Catalogue::money($product['promotion']['base_price_cents'], $locale) }}</s></span>
    @endif
</p>
