@props(['locale', 'open' => false, 'id' => null, 'productName' => null])
<details class="purchase-information" @if($id) id="{{ $id }}" @endif @if($open) open @endif>
    <summary>{{ __('commerce.purchase_conditions') }}@if($productName)<span class="purchase-product-name"> — {{ $productName }}</span>@endif</summary>
    <ul>
        <li><span aria-hidden="true">€</span><div><strong>{{ __('commerce.clear_price') }}</strong><p>{{ __('commerce.clear_price_help') }}</p></div></li>
        <li><span aria-hidden="true">↗</span><div><a href="{{ route('information', ['locale' => $locale, 'page' => 'livraison']) }}"><strong>{{ __('commerce.shipping_free') }}</strong></a><p>{{ __('commerce.delivery_estimate') }}</p></div></li>
        <li><span aria-hidden="true">✓</span><div><strong>{{ __('commerce.payment_before_preparation') }}</strong><p>{{ __('commerce.payment_before_preparation_help') }} <a href="{{ route('information', ['locale' => $locale, 'page' => 'paiement']) }}">{{ __('commerce.payment_information') }}</a></p></div></li>
        <li><span aria-hidden="true">↶</span><div><a href="{{ route('information', ['locale' => $locale, 'page' => 'retractation']) }}"><strong>{{ __('commerce.free_returns') }}</strong></a><p>{{ __('commerce.return_conditions') }}</p></div></li>
    </ul>
    <p class="purchase-stock-notice">{{ __('commerce.cart_reservation_notice') }}</p>
</details>
