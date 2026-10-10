<x-layout :title="__('commerce.checkout').' — HOMYA'" :catalogue-ready="true">
    <main id="main" class="shell commerce-page checkout-page" tabindex="-1">
        <a class="commerce-back" href="{{ route('cart.show', ['locale' => $locale]) }}">← {{ __('commerce.back_cart') }}</a>
        <h1>{{ __('commerce.checkout') }}</h1>
        <x-checkout-steps current="checkout" :locale="$locale" />
        <x-commerce-errors />
        @if(session('commerce_message'))<p class="commerce-success" role="status">{{ session('commerce_message') }}</p>@endif
        <p class="checkout-intro">{{ __(!$bankReady ? 'commerce.deferred_notice' : 'commerce.checkout_intro') }}</p>
        <div class="commerce-grid checkout-grid">
            <div class="checkout-main">
            <form id="checkout-form" class="checkout-form" method="post" action="{{ route('checkout.store', ['locale' => $locale]) }}">@csrf
                <input type="hidden" name="checkout_key" value="{{ $key }}"><input type="hidden" name="quote_hash" value="{{ $quoteHash }}">
                <fieldset class="checkout-panel"><legend><span class="checkout-section-number" aria-hidden="true">1</span> {{ __('commerce.contact_details') }}</legend>
                    <div class="checkout-fields">
                        <x-checkout-field name="name" autocomplete="name" :max="150" />
                        <x-checkout-field name="email" type="email" autocomplete="email" :max="254" />
                    </div>
                </fieldset>
                <fieldset class="checkout-panel"><legend><span class="checkout-section-number" aria-hidden="true">2</span> {{ __('commerce.shipping_address') }}</legend>
                    <x-checkout-field name="address" autocomplete="street-address" :max="200" />
                    <div class="address-lookup" data-address-lookup data-url="{{ route('checkout.addresses', ['locale' => $locale]) }}" data-empty="{{ __('commerce.address_empty') }}" data-failure="{{ __('commerce.address_failure') }}" data-loading="{{ __('commerce.address_loading') }}" data-selected="{{ __('commerce.address_selected') }}" data-count="{{ __('commerce.address_count') }}" hidden>
                        <ul id="checkout-address-suggestions" class="address-suggestions" data-address-results role="listbox" aria-label="{{ __('commerce.address_results') }}" hidden></ul>
                        <p id="checkout-address-help" class="checkout-help">{{ __('commerce.address_hint') }}</p>
                        <div class="address-lookup-controls">
                            <label><input type="checkbox" data-address-auto checked> {{ __('commerce.address_auto') }}</label>
                            <button type="button" class="button-secondary" data-address-search>{{ __('commerce.address_search') }}</button>
                        </div>
                        <p class="checkout-help">{{ __('commerce.address_privacy') }}</p>
                        <p data-address-status role="status" aria-live="polite" aria-atomic="true"></p>
                    </div>
                    <div class="checkout-fields">
                        <x-checkout-field name="postal_code" autocomplete="postal-code" :max="12" />
                        <x-checkout-field name="city" autocomplete="address-level2" :max="100" />
                    </div>
                    <div class="checkout-field"><label for="checkout-country">{{ __('commerce.country') }}</label><select id="checkout-country" name="country" autocomplete="country" required @if($errors->has('country')) aria-invalid="true" aria-describedby="checkout-country-error" @endif>@foreach(config('commerce.countries') as $country)<option value="{{ $country }}" @selected(old('country', 'FR') === $country)>{{ __('commerce.countries.'.$country) }}</option>@endforeach</select>
                        @error('country')<p id="checkout-country-error" class="checkout-field-error">{{ $message }}</p>@enderror
                    </div>
                    <p class="checkout-help">{{ __('commerce.shipping_free') }} {{ __('commerce.delivery_estimate') }}</p>
                </fieldset>
                <fieldset class="checkout-panel payment-options"><legend><span class="checkout-section-number" aria-hidden="true">3</span> {{ __('commerce.payment_method') }}</legend>
                    @if($bankReady)<label class="payment-choice"><input type="radio" name="payment_method" value="bank_transfer" checked required><span><strong>{{ __('commerce.bank_method') }}</strong><small>{{ __('commerce.bank_next_step') }}</small></span></label><p class="checkout-help">{{ __('commerce.bank_deadline_notice') }}</p>@endif
                    @if(!$bankReady)<input type="hidden" name="payment_method" value="deferred"><strong>{{ __('commerce.status.pending_payment_details') }}</strong><p class="checkout-help">{{ __('commerce.deferred_notice') }}</p><p class="checkout-help">{{ __('commerce.deferred_reservation_notice') }}</p>@endif
                    @error('payment_method')<p class="checkout-field-error" role="alert">{{ $message }}</p>@enderror
                </fieldset>
                <div class="checkout-panel checkout-consent">
                    <label class="terms-checkbox"><input type="checkbox" name="terms" value="1" required @if($errors->has('terms')) aria-invalid="true" aria-describedby="checkout-terms-error" @endif><span>{{ __('commerce.accept_terms') }} <a href="{{ route('information', ['locale' => $locale, 'page' => 'cgv']) }}">{{ __('commerce.terms') }}</a>.</span></label>
                    @error('terms')<p id="checkout-terms-error" class="checkout-field-error">{{ $message }}</p>@enderror
                    <p class="checkout-help"><a href="{{ route('information', ['locale' => $locale, 'page' => 'confidentialite']) }}">{{ __('commerce.privacy') }}</a></p>
                </div>
            </form>
                @if($bankReady)<x-bank-transfer-guide :locale="$locale" :ready="true" />@endif
            </div>
            <aside class="commerce-summary checkout-summary" aria-labelledby="checkout-summary-heading">
                <div class="checkout-summary-heading"><h2 id="checkout-summary-heading">{{ __('commerce.summary') }}</h2><a href="{{ route('cart.show', ['locale' => $locale]) }}">{{ __('commerce.edit_cart') }}</a></div>
                <ul class="checkout-items">@foreach($quote['lines'] as $line)<li>
                    <x-product-image :src="$line['product']['image']['local_url'] ?? null" alt="" sizes="64px" />
                    <div><strong>{{ $line['product']['name'] }}</strong>@if($line['variant_name'])<span>{{ $line['variant_name'] }}</span>@endif<span>{{ __('commerce.quantity') }} : {{ $line['quantity'] }}</span><b>{{ \App\Services\Catalogue::money($line['line_cents'], $locale) }}</b></div>
                </li>@endforeach</ul>
                <dl class="checkout-totals"><div><dt>{{ __('commerce.subtotal') }}</dt><dd>{{ \App\Services\Catalogue::money($quote['total_cents'], $locale) }}</dd></div><div><dt>{{ __('commerce.delivery') }}</dt><dd>{{ __('commerce.free_delivery') }}</dd></div><div class="checkout-total"><dt>{{ __('commerce.total_tax_included') }}</dt><dd>{{ \App\Services\Catalogue::money($quote['total_cents'], $locale) }}</dd></div></dl>
                <div class="checkout-next-step"><strong>{{ __('commerce.next_step') }}</strong>
                    @if($bankReady)<p>{{ __('commerce.bank_next_step') }}</p>@endif
                    @if(!$bankReady)<p>{{ __('commerce.deferred_notice') }}</p>@endif
                </div>
                <button class="button-primary checkout-submit" type="submit" form="checkout-form" data-busy-label="{{ __('commerce.validating') }}"><span data-submit-label>{{ __('commerce.confirm_order_action') }}</span><strong>{{ \App\Services\Catalogue::money($quote['total_cents'], $locale) }}</strong></button>
                <p class="checkout-help">{{ __('commerce.validation_help') }}</p>
                <p class="checkout-return"><a href="{{ route('information', ['locale' => $locale, 'page' => 'retractation']) }}">{{ __('commerce.free_returns') }}</a></p>
                <p class="checkout-help"><a href="{{ route('information', ['locale' => $locale, 'page' => 'contact']) }}">{{ __('commerce.need_help') }}</a></p>
            </aside>
        </div>
        <x-purchase-help :locale="$locale" />
    </main>
</x-layout>
