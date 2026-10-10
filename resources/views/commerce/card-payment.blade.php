<x-layout :title="__('commerce.card_form.title').' — HOMYA'" :catalogue-ready="true">
    <main id="main" class="shell commerce-page card-payment-page" tabindex="-1">
        <p class="eyebrow">{{ __('commerce.card_method') }}</p>
        <h1>{{ __('commerce.card_form.title') }}</h1>
        <p id="card-configuration-status" class="checkout-intro">{{ __('commerce.card_form.intro') }}</p>
        <div class="card-payment-grid">
            <section class="checkout-panel card-payment-panel" aria-labelledby="card-form-heading">
                <h2 id="card-form-heading">{{ __('commerce.card_form.card_details') }}</h2>
                <x-card-payment-preview />
                <button class="button-primary" type="button" disabled aria-describedby="card-configuration-status">{{ __('commerce.card_form.pay') }}</button>
                <div class="card-security-note"><span aria-hidden="true">🔒</span><p>{{ __('commerce.card_form.security') }}</p></div>
            </section>
            <aside class="commerce-summary card-payment-summary">
                @if($order)
                    <h2>{{ __('commerce.summary') }}</h2><p class="order-reference">{{ __('commerce.order_reference') }} {{ $order->id }}</p>
                    <ul class="order-items">@foreach($items as $item)<li>{{ $item->name }} × {{ $item->quantity }} — {{ \App\Services\Catalogue::money($item->line_cents, $locale) }}</li>@endforeach</ul>
                    <p class="card-payment-total"><span>{{ __('commerce.total_tax_included') }}</span><strong>{{ \App\Services\Catalogue::money($order->total_cents, $locale) }}</strong></p>
                    <a href="{{ route('order.show', ['locale' => $locale, 'id' => $order->id]) }}">{{ __('commerce.card_form.back_order') }}</a>
                @else
                    <h2>{{ __('commerce.card_form.how_title') }}</h2><p>{{ __('commerce.card_form.how') }}</p>
                    <a class="button-secondary" href="{{ route('cart.show', ['locale' => $locale]) }}">{{ __('commerce.cart') }}</a>
                @endif
                <p class="checkout-help">{{ __('commerce.card_form.confirmation') }}</p>
                <a href="{{ route('information', ['locale' => $locale, 'page' => 'confidentialite']) }}">{{ __('commerce.privacy') }}</a>
            </aside>
        </div>
    </main>
</x-layout>
