<x-layout :title="__('commerce.order').' — HOMYA'" :catalogue-ready="true">
    <main id="main" class="shell commerce-page order-page" tabindex="-1">
        <x-checkout-steps current="confirmation" :locale="$locale" />

        <header class="order-page-heading">
            <p class="order-eyebrow">HOMYA · {{ __('commerce.confirmation') }}</p>
            <h1>{{ __('commerce.order') }}</h1>
        </header>

        <x-commerce-errors />

        <section class="order-status-card" aria-label="{{ __('commerce.order') }}">
            <span class="order-status-mark" aria-hidden="true">
                @if($order->status === 'paid')
                    <svg viewBox="0 0 24 24" fill="none"><path d="m5 12.5 4.2 4.2L19 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                @elseif(in_array($order->status, ['cancelled', 'expired'], true))
                    <svg viewBox="0 0 24 24" fill="none"><path d="M7 7l10 10M17 7 7 17" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                @else
                    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8"/><path d="M12 7v5l3 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                @endif
            </span>
            <div class="order-status-copy">
                <span class="order-status-label">{{ __('commerce.order') }}</span>
                <strong class="order-status order-status--{{ $order->status }}" role="status">{{ $order->status === 'cancelled' ? __('support.cancelled') : __('commerce.status.'.$order->status) }}</strong>
            </div>
            <div class="order-reference-card">
                <span>{{ __('commerce.order_reference') }}</span>
                <strong>{{ $order->id }}</strong>
            </div>
        </section>

        @if($order->status === 'pending_payment_details')
            <section class="order-next-step">
                <span class="order-next-step-icon" aria-hidden="true">i</span>
                <div>
                    <h2>{{ __('commerce.deferred_confirmation') }}</h2>
                    <p>{{ __('commerce.deferred_confirmation_notice') }}</p>
                    <p class="order-deadline">{{ __('commerce.deferred_deadline', ['deadline' => $deadline->format('d/m/Y H:i')]) }}</p>
                    <p>{{ __('commerce.deferred_contact') }}</p>
                </div>
            </section>
        @endif

        @if(config('dashboard.enabled') && ($shipment = app(\App\Services\Admin\Deliveries::class)->get($order->id)))
            <section class="checkout-panel order-shipment">
                <h2>{{ __('support.delivery') }}</h2>
                <p>{{ __('support.'.$shipment->status) }}</p>
                @if($shipment->carrier)<p>{{ $shipment->carrier }} · {{ $shipment->tracking_number }}</p>@endif
                @if($shipment->tracking_url)<a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener noreferrer">{{ __('support.tracking') }}</a>@endif
            </section>
        @endif

        <section class="order-summary-card" aria-labelledby="order-summary-title">
            <div class="order-summary-heading">
                <div>
                    <span class="order-eyebrow">{{ __('commerce.confirmation') }}</span>
                    <h2 id="order-summary-title">{{ __('commerce.summary') }}</h2>
                </div>
                <span class="order-item-count">{{ count($items) }} · {{ __('commerce.items') }}</span>
            </div>

            <ul class="order-items">
                @foreach($items as $item)
                    <li class="order-item">
                        <span class="order-item-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none"><path d="m4 8 8-4 8 4-8 4-8-4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m4 12 8 4 8-4M4 16l8 4 8-4M12 12v8" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>
                        </span>
                        <div class="order-item-details">
                            <strong>{{ $item->name }}@if($item->variant_name) — {{ $item->variant_name }}@endif</strong>
                            <span>{{ __('commerce.order_reference') }} {{ $item->reference }}</span>
                            <span>{{ __('commerce.quantity') }} : {{ $item->quantity }}</span>
                        </div>
                        <strong class="order-item-price">{{ \App\Services\Catalogue::money($item->line_cents, $locale) }}</strong>
                    </li>
                @endforeach
            </ul>

            <div class="order-total">
                <span>{{ __('commerce.total_tax_included') }}</span>
                <strong>{{ \App\Services\Catalogue::money($order->total_cents, $locale) }}</strong>
            </div>
        </section>

        @if($order->status === 'paid')<p class="order-paid-help">{{ __('commerce.paid_help') }}</p>@endif

        <section class="order-actions" aria-label="{{ __('commerce.order') }}">
            <div class="payment-actions">
                @if($order->status === 'pending_payment')<p>{{ __('commerce.pending_help') }}</p>@endif
                @if($order->status === 'pending_payment' && $bankReady)
                    <form method="post" data-commerce-action action="{{ route('order.retry', ['locale' => $locale, 'id' => $order->id]) }}">@csrf<button class="button-primary" type="submit" data-busy-label="{{ __('commerce.validating') }}"><span data-submit-label>{{ __('commerce.resume_payment') }}</span></button></form>
                @endif
                @if($order->status === 'pending_bank_transfer')
                    <a class="button-primary" href="{{ route('order.bank', ['locale' => $locale, 'id' => $order->id]) }}">{{ __('commerce.bank_view_details') }}</a>
                @endif
                @if($order->status === 'pending_payment_details' && $bankReady)
                    <form method="post" data-commerce-action action="{{ route('order.choose-bank', ['locale' => $locale, 'id' => $order->id]) }}">@csrf<button class="button-primary" type="submit" data-busy-label="{{ __('commerce.validating') }}"><span data-submit-label>{{ __('commerce.validate_order') }}</span></button></form>
                @endif
                @if($order->status === 'pending_payment' && $bankReady)
                    <form method="post" data-commerce-action action="{{ route('order.choose-bank', ['locale' => $locale, 'id' => $order->id]) }}">@csrf<button class="button-secondary" type="submit" data-busy-label="{{ __('commerce.validating') }}"><span data-submit-label>{{ __('commerce.bank_switch') }}</span></button></form>
                @endif
            </div>

            <div class="order-secondary-actions">
                <a class="button-secondary" href="{{ route('order.document', ['locale' => $locale, 'id' => $order->id, 'kind' => 'summary']) }}">
                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3.75h7l4.25 4.5v12H7a2 2 0 0 1-2-2v-12a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7"/><path d="M14 4v5h4M8 13h6M8 16h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                    {{ __('commerce.pdf_summary') }} (PDF)
                </a>
                <a class="button-secondary" href="{{ route('information', ['locale' => $locale, 'page' => 'contact']) }}">{{ __('chrome.contact') }}</a>
                <a class="order-continue" href="{{ route('catalogue', ['locale' => $locale]) }}">{{ __('commerce.continue') }} <span aria-hidden="true">→</span></a>
            </div>
        </section>

        @include('commerce.commercial-documents')
    </main>
</x-layout>
