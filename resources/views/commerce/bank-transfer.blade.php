<x-layout :title="__('commerce.bank_instructions').' — HOMYA'" :catalogue-ready="true">
    <main id="main" class="shell commerce-page bank-confirmation" tabindex="-1">
        <x-checkout-steps current="confirmation" :locale="$locale" />
        <h1>{{ __('commerce.bank_confirmation') }}</h1>
        <p class="checkout-intro">{{ __('commerce.bank_confirmation_intro') }}</p>
        <p class="order-reference">{{ __('commerce.order_reference') }} {{ $order->id }}</p>
        <x-commerce-errors />
        <div class="bank-confirmation-card">
            <p class="bank-confirmation-total"><span>{{ __('commerce.bank_amount') }}</span><strong>{{ \App\Services\Catalogue::money($order->total_cents, $locale) }}</strong></p>
            @if(session('commerce_message'))<p class="commerce-success" role="status">{{ session('commerce_message') }}</p>@endif
            <section class="commerce-summary" aria-labelledby="bank-details-heading">
                <h2 id="bank-details-heading">{{ __('commerce.bank_how_to') }}</h2>
                @if($active && $snapshot)
                    <p role="status">{{ __('commerce.bank_recorded') }}</p>
                    <p class="bank-deadline"><strong>{{ __('commerce.bank_due') }} <time datetime="{{ $deadline->toIso8601String() }}">{{ $deadline->format('d/m/Y H:i:s') }} (Europe/Paris)</time></strong></p>
                    <p>{{ __('commerce.bank_deadline_notice') }}</p>
                    <details class="bank-accordion" open><summary><span class="checkout-section-number" aria-hidden="true">1</span> {{ __('commerce.bank_show_details') }}</summary>
                    <dl class="bank-details">
                        @foreach(['beneficiary', 'bank_name', 'iban', 'bic'] as $field)
                            <div><dt>{{ __('commerce.bank_'.$field) }}</dt><dd><span id="bank-{{ $field }}" @if(in_array($field, ['iban', 'bic'])) dir="ltr" @endif>{{ $snapshot['details'][$field] }}</span><button class="button-secondary bank-copy" type="button" data-copy-target="bank-{{ $field }}" data-copy-success="{{ __('commerce.copied') }}" data-copy-failure="{{ __('commerce.copy_failed') }}" hidden>{{ __('commerce.copy') }}<span class="sr-only"> {{ __('commerce.bank_'.$field) }}</span></button></dd></div>
                        @endforeach
                        <div><dt>{{ __('commerce.bank_amount') }}</dt><dd>{{ \App\Services\Catalogue::money($order->total_cents, $locale) }} · EUR</dd></div>
                        <div><dt>{{ __('commerce.bank_reference') }}</dt><dd><span id="bank-reference">{{ $order->id }}</span><button class="button-secondary bank-copy" type="button" data-copy-target="bank-reference" data-copy-success="{{ __('commerce.copied') }}" data-copy-failure="{{ __('commerce.copy_failed') }}" hidden>{{ __('commerce.copy') }}<span class="sr-only"> {{ __('commerce.bank_reference') }}</span></button></dd></div>
                    </dl>
                    <p id="bank-copy-feedback" role="status"></p>
                    <a class="bank-download" href="{{ route('order.document', ['locale' => $locale, 'id' => $order->id, 'kind' => 'bank']) }}">↓ {{ __('commerce.pdf_bank') }} (PDF)</a></details>
                    <details class="bank-accordion"><summary><span class="checkout-section-number" aria-hidden="true">2</span> {{ __('commerce.bank_make_transfer') }}</summary>
                    <p>{{ __('commerce.bank_beneficiary_help') }}</p>
                    <p>{{ __('commerce.bank_step_transfer') }}</p>
                    <p>{{ __('commerce.bank_reference_help') }}</p>
                    <p>{{ __('commerce.bank_receipt_notice') }}</p></details>
                    <section class="bank-transfer-notice"><h3>{{ __('commerce.bank_processing_title') }}</h3><p>{{ __('commerce.bank_processing_help') }}</p></section>
                    <details class="bank-accordion bank-proof" @if($errors->has('proof')) open @endif><summary>{{ __('commerce.proof_action') }}</summary>
                        <p>{{ __('commerce.proof_help') }}</p>
                        <form method="post" enctype="multipart/form-data" data-commerce-action action="{{ route('order.proof', ['locale' => $locale, 'id' => $order->id]) }}">@csrf
                            <label for="transfer-proof">{{ __('commerce.proof_label') }}</label>
                            <input type="file" id="transfer-proof" name="proof" accept="application/pdf,image/png,image/jpeg" required>
                            @error('proof')<p class="checkout-field-error" role="alert">{{ $message }}</p>@enderror
                            <button type="submit" class="button-primary" data-busy-label="{{ __('commerce.validating') }}"><span data-submit-label>{{ __('commerce.proof_action') }}</span></button>
                        </form>
                    </details>
                @elseif(in_array($order->status, ['expired', 'bank_transfer_expired'], true))
                    <p role="status">{{ __('commerce.bank_expired') }}</p>
                @elseif(!$bankReady)
                    <p role="status">{{ __('commerce.bank_details_pending') }}</p>
                    <ul>@foreach(['beneficiary', 'bank_name', 'iban', 'bic'] as $field)<li>{{ __('commerce.bank_'.$field) }}</li>@endforeach</ul>
                @else
                    <p role="status">{{ __('commerce.bank_verification') }}</p>
                    <form method="post" data-commerce-action action="{{ route('order.choose-bank', ['locale' => $locale, 'id' => $order->id]) }}">@csrf<button class="button-primary" type="submit" data-busy-label="{{ __('commerce.validating') }}"><span data-submit-label>{{ __('commerce.bank_retry') }}</span></button></form>
                @endif
                <p><a href="{{ route('information', ['locale' => $locale, 'page' => 'contact']) }}">{{ __('chrome.contact') }}</a></p>
            </section>
            @if($active && $snapshot)
                <div class="bank-finish-order">
                    <a class="button-primary" href="{{ route('order.show', ['locale' => $locale, 'id' => $order->id]) }}">{{ __('commerce.finish_order') }}</a>
                    <p>{{ __('commerce.bank_recorded') }}</p>
                </div>
            @endif
            <section class="bank-order-summary">
                <a class="bank-download" href="{{ route('order.document', ['locale' => $locale, 'id' => $order->id, 'kind' => 'summary']) }}">↓ {{ __('commerce.pdf_summary') }} (PDF)</a>
                <details class="bank-accordion"><summary>{{ __('commerce.order_details') }}</summary>
                <h2>{{ __('commerce.summary') }}</h2>
                <ul>@foreach($items as $item)<li>{{ $item->name }}@if($item->variant_name) — {{ $item->variant_name }}@endif × {{ $item->quantity }} — {{ \App\Services\Catalogue::money($item->line_cents, $locale) }}</li>@endforeach</ul>
                <p><strong>{{ __('commerce.total_tax_included') }} : {{ \App\Services\Catalogue::money($order->total_cents, $locale) }}</strong></p>
                <p>{{ __('commerce.shipping_free') }}</p>
                <a class="button-secondary" href="{{ route('order.show', ['locale' => $locale, 'id' => $order->id]) }}">{{ __('commerce.view_order') }}</a>
                </details>
            </section>
        </div>
        <a class="commerce-back" href="{{ route('cart.show', ['locale' => $locale]) }}">{{ __('commerce.cart') }}</a>
    </main>
</x-layout>
