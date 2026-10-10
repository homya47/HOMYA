@props(['locale', 'ready' => false])
<section class="bank-transfer-guide" aria-labelledby="bank-transfer-guide-heading">
    <h2 id="bank-transfer-guide-heading">{{ __('commerce.bank_cart_title') }}</h2>
    <p class="bank-guide-intro">{{ __('commerce.bank_cart_intro') }}</p>
    @unless($ready)<p class="bank-guide-availability" role="note">{{ __('commerce.bank_cart_unavailable') }}</p>@endunless
    <ol>
        <li><span class="bank-guide-step" aria-hidden="true">1</span><div><h3>{{ __('commerce.bank_cart_details_title') }}</h3><p>{{ __('commerce.bank_next_step') }}</p></div></li>
        <li><span class="bank-guide-step" aria-hidden="true">2</span><div><h3>{{ __('commerce.bank_cart_transfer_title') }}</h3><p>{{ __('commerce.bank_step_transfer') }}</p><p>{{ __('commerce.bank_deadline_notice') }} {{ __('commerce.bank_cart_expiry') }}</p></div></li>
        <li><span class="bank-guide-step" aria-hidden="true">3</span><div><h3>{{ __('commerce.bank_cart_preparation_title') }}</h3><p>{{ __('commerce.bank_step_wait') }} {{ __('commerce.delivery_estimate') }}</p></div></li>
    </ol>
    <a class="bank-guide-link" href="{{ route('information', ['locale' => $locale, 'page' => 'paiement']) }}">{{ __('commerce.payment_information') }} <span aria-hidden="true">→</span></a>
</section>
