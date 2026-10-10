@props(['productName' => null, 'disabled' => false])
<button {{ $attributes->merge(['class' => 'button-primary button-add-cart', 'type' => 'submit']) }} @disabled($disabled)>
    <svg aria-hidden="true" focusable="false" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l2.4 12h11.2l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
    <span>{{ __('commerce.add') }}@if($productName)<span class="sr-only"> — {{ $productName }}</span>@endif</span>
</button>
