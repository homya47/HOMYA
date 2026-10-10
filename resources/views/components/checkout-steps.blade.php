@props(['current' => 'cart', 'locale'])
@php($currentIndex = array_search($current, ['cart', 'checkout', 'confirmation'], true))
<nav class="checkout-progress" aria-label="{{ __('commerce.purchase_steps') }}">
    <ol>
        @foreach(['cart' => 'cart', 'checkout' => 'checkout', 'confirmation' => 'confirmation'] as $step => $label)
            <li @if($current === $step) aria-current="step" @endif @class(['step-complete' => $currentIndex !== false && $loop->index < $currentIndex])><span aria-hidden="true">{{ $currentIndex !== false && $loop->index < $currentIndex ? '✓' : $loop->iteration }}</span>{{ __('commerce.'.$label) }}</li>
        @endforeach
    </ol>
</nav>
