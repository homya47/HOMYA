@props(['prefix' => 'card-preview'])
<fieldset disabled class="card-preview-fields" aria-describedby="card-configuration-status">
    <legend>{{ __('commerce.card_form.card_details') }}</legend>
    <div class="checkout-fields">
        @foreach(['holder', 'number', 'expiry', 'cvc', 'email', 'billing_address', 'postal_code', 'city', 'country'] as $field)
            <div @class(['checkout-field', 'card-field-wide' => in_array($field, ['holder', 'number', 'billing_address'])])>
                <label for="{{ $prefix }}-{{ $field }}">{{ __('commerce.card_form.'.$field) }} <span aria-hidden="true">*</span></label>
                <input id="{{ $prefix }}-{{ $field }}" type="text" autocomplete="off" disabled placeholder="{{ __('commerce.card_form.placeholders.'.$field) }}">
            </div>
        @endforeach
    </div>
</fieldset>
<p class="checkout-help">{{ __('commerce.card_form.required') }}</p>
