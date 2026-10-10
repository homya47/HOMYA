@props(['locale'])
<section class="return-overview" aria-labelledby="return-overview-heading">
    <h2 id="return-overview-heading">{{ __('returns.overview') }}</h2>
    <dl class="return-facts">
        <div><dt>{{ __('returns.window_label') }}</dt><dd>{{ __('returns.window') }}</dd></div>
        <div><dt>{{ __('returns.cost_label') }}</dt><dd>{{ __('returns.cost') }}</dd></div>
        <div><dt>{{ __('returns.destination_label') }}</dt><dd>{{ __('returns.destination') }}</dd></div>
    </dl>
    <p>{{ __('returns.overview_notice') }}</p>
    <a class="button-primary" href="{{ route('returns.create', ['locale' => $locale]) }}">{{ __('returns.start') }} <span aria-hidden="true">→</span></a>
    <p class="return-logistics-notice">{{ __('returns.logistics_notice') }}</p>
</section>
