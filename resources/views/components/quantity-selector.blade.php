@props(['id', 'value' => 1, 'max' => 99])
@php($value = is_scalar($value) ? $value : 1)
<div class="quantity-field">
    <label for="{{ $id }}">{{ __('commerce.quantity') }}</label>
    <div class="quantity-control" data-quantity-control>
        <button type="button" data-quantity-step="-1" aria-label="{{ __('commerce.decrease_quantity') }}" aria-controls="{{ $id }}" hidden>−</button>
        <input id="{{ $id }}" type="number" name="quantity" value="{{ $value }}" min="1" max="{{ $max }}" inputmode="numeric" required>
        <button type="button" data-quantity-step="1" aria-label="{{ __('commerce.increase_quantity') }}" aria-controls="{{ $id }}" hidden>+</button>
    </div>
</div>
