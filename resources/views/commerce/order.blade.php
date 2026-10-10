<x-layout :title="__('commerce.order').' — HOMYA'" :catalogue-ready="true">
    <main id="main" class="shell commerce-page" tabindex="-1">
        <x-checkout-steps current="confirmation" :locale="$locale" />
        <h1>{{ __('commerce.order') }}</h1><p class="order-reference">{{ __('commerce.order_reference') }} {{ $order->id }}</p><p class="order-status" role="status">{{ $order->status === 'cancelled' ? __('support.cancelled') : __('commerce.status.'.$order->status) }}</p><x-commerce-errors />
        @if($order->status === 'pending_payment_details')<section class="checkout-panel"><h2>{{ __('commerce.deferred_confirmation') }}</h2><p>{{ __('commerce.deferred_notice') }}</p><p>{{ __('commerce.deferred_deadline', ['deadline' => $deadline->format('d/m/Y H:i')]) }}</p><p>{{ __('commerce.deferred_contact') }}</p></section>@endif
        @if(config('dashboard.enabled') && ($shipment = app(\App\Services\Admin\Deliveries::class)->get($order->id)))<section class="checkout-panel"><h2>{{ __('support.delivery') }}</h2><p>{{ __('support.'.$shipment->status) }}</p>@if($shipment->carrier)<p>{{ $shipment->carrier }} · {{ $shipment->tracking_number }}</p>@endif @if($shipment->tracking_url)<a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener noreferrer">{{ __('support.tracking') }}</a>@endif</section>@endif
        <ul class="order-items">@foreach($items as $item)<li>{{ $item->name }}@if($item->variant_name) — {{ $item->variant_name }}@endif — {{ $item->reference }} × {{ $item->quantity }} — {{ \App\Services\Catalogue::money($item->line_cents, $locale) }}</li>@endforeach</ul>
        <p><strong>{{ __('commerce.total_tax_included') }} : {{ \App\Services\Catalogue::money($order->total_cents, $locale) }}</strong></p>
        <div class="payment-actions">
            @if(in_array($order->status, ['pending_payment', 'pending_payment_details'], true))<a class="button-secondary" href="{{ route('order.card', ['locale' => $locale, 'id' => $order->id]) }}">{{ __('commerce.card_form.title') }}</a>@endif
            @if($order->status === 'pending_payment')<p>{{ __('commerce.pending_help') }}</p>@endif
            @if(in_array($order->status, ['pending_payment', 'pending_payment_details'], true) && (($cardReady ?? false) || $bankReady))<form method="post" data-commerce-action action="{{ route('order.retry', ['locale' => $locale, 'id' => $order->id]) }}">@csrf<button class="button-primary" type="submit" data-busy-label="{{ __('commerce.validating') }}"><span data-submit-label>{{ __('commerce.resume_payment') }}</span></button></form>@endif
            @if($order->status === 'pending_bank_transfer')<a class="button-primary" href="{{ route('order.bank', ['locale' => $locale, 'id' => $order->id]) }}">{{ __('commerce.bank_view_details') }}</a>@endif
            @if(in_array($order->status, ['pending_payment', 'pending_payment_details'], true) && $bankReady)<form method="post" data-commerce-action action="{{ route('order.choose-bank', ['locale' => $locale, 'id' => $order->id]) }}">@csrf<button class="button-secondary" type="submit" data-busy-label="{{ __('commerce.validating') }}"><span data-submit-label>{{ __('commerce.bank_switch') }}</span></button></form>@endif
        </div>
        @if($order->status === 'paid')<p>{{ __('commerce.paid_help') }}</p>@endif
        @if($order->status === 'pending_payment_details')<p><a href="{{ route('order.document', ['locale' => $locale, 'id' => $order->id, 'kind' => 'summary']) }}">{{ __('commerce.pdf_summary') }} (PDF)</a></p>@endif
        <p><a href="{{ route('information', ['locale' => $locale, 'page' => 'contact']) }}">{{ __('chrome.contact') }}</a></p><a class="commerce-back" href="{{ route('catalogue', ['locale' => $locale]) }}">{{ __('commerce.continue') }}</a>
        @include('commerce.commercial-documents')
    </main>
</x-layout>
