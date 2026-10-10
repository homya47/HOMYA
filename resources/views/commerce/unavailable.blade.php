<x-layout :title="__('commerce.checkout').' — HOMYA'">
    <main id="main" class="shell commerce-page" tabindex="-1"><h1>{{ __('commerce.checkout') }}</h1><p class="intro">{{ __('commerce.sales_unavailable') }}</p><a class="button-primary" href="{{ route('cart.show', ['locale' => app()->getLocale()]) }}">{{ __('commerce.cart') }}</a></main>
</x-layout>
