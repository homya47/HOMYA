<x-layout :title="__('tracking.title').' — HOMYA'" :description="__('tracking.intro')" :catalogue-ready="true" :language-links="collect(config('homya.locales'))->mapWithKeys(fn ($language) => [$language => route('order.tracking', ['locale' => $language])])->all()">
    <main id="main" class="shell commerce-page" tabindex="-1">
        <header class="information-heading"><p class="eyebrow">HOMYA</p><h1>{{ __('tracking.title') }}</h1><p>{{ __('tracking.intro') }}</p></header>
        <div class="commerce-grid">
            <section class="commerce-summary" aria-labelledby="tracking-form-heading">
                <h2 id="tracking-form-heading">{{ __('tracking.lookup') }}</h2>
                <form method="post" action="{{ route('order.tracking.lookup', ['locale' => $locale]) }}" data-commerce-action>
                    @csrf
                    <div class="checkout-field">
                        <label for="order-reference">{{ __('tracking.reference') }}</label>
                        <input id="order-reference" name="reference" type="text" value="{{ $reference }}" minlength="36" maxlength="36" required autocapitalize="none" spellcheck="false" aria-describedby="tracking-help" @if($searched && !$tracking) aria-invalid="true" @endif>
                        <p id="tracking-help" class="checkout-help">{{ __('tracking.help') }}</p>
                    </div>
                    <button class="button-primary" type="submit" data-busy-label="{{ __('commerce.validating') }}"><span data-submit-label>{{ __('tracking.submit') }}</span></button>
                </form>
                @if($searched && !$tracking)<p class="checkout-field-error" role="alert">{{ __('tracking.not_found') }}</p>@endif
                @if($tracking)
                    <section aria-labelledby="tracking-result-heading" aria-live="polite">
                        <h2 id="tracking-result-heading">{{ __('tracking.result') }}</h2>
                        <p class="order-reference">{{ __('tracking.reference') }} : {{ $tracking['reference'] }}</p>
                        <p class="order-status" role="status">{{ __('tracking.stages.'.$tracking['stage']) }}</p>
                        <p>{{ __('tracking.details.'.$tracking['stage']) }}</p>
                        @unless(in_array($tracking['stage'], ['cancelled', 'expired'], true))
                            @php($stages = ['registered', 'paid', 'processing', 'shipped', 'delivered'])
                            @php($current = array_search($tracking['stage'], $stages, true))
                            <nav class="checkout-progress" aria-label="{{ __('tracking.progress') }}"><ol>
                                @foreach($stages as $stage)<li @class(['step-complete' => $loop->index < $current]) @if($loop->index === $current) aria-current="step" @endif><span aria-hidden="true">{{ $loop->index < $current ? '✓' : $loop->iteration }}</span>{{ __('tracking.stages.'.$stage) }}</li>@endforeach
                            </ol></nav>
                        @endunless
                    </section>
                @endif
            </section>
            <aside class="commerce-summary"><h2>{{ __('tracking.assistance') }}</h2><p>{{ __('tracking.assistance_help') }}</p><x-contact-actions /></aside>
        </div>
    </main>
</x-layout>
