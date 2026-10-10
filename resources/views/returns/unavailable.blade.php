<x-layout :title="__('returns.unavailable').' — HOMYA'" :description="__('returns.unavailable_notice')">
    <main id="main" class="shell returns-page" tabindex="-1">
        <header class="information-heading"><p class="eyebrow">HOMYA</p><h1>{{ __('returns.unavailable') }}</h1><p>{{ __('returns.unavailable_notice') }}</p></header>
        <div class="return-form-card"><a class="button-primary" href="{{ route('returns.create', ['locale' => app()->getLocale()]) }}">{{ __('returns.start') }}</a><x-return-details /><a href="{{ route('information', ['locale' => app()->getLocale(), 'page' => 'retractation']) }}">{{ __('pages.entries.retractation.title') }}</a></div>
    </main>
</x-layout>
