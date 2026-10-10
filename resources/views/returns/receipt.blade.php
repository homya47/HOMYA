<x-layout :title="__('returns.received').' — HOMYA'" :description="__('returns.receipt_notice')" :catalogue-ready="true">
    <main id="main" class="shell returns-page" tabindex="-1">
        <header class="information-heading"><p class="eyebrow">HOMYA</p><h1>{{ __('returns.received') }}</h1><p>{{ __('returns.receipt_notice') }}</p></header>
        <div class="return-form-card">
            <dl class="company-details"><div><dt>{{ __('returns.reference') }}</dt><dd>{{ $record['id'] }}</dd></div><div><dt>{{ __('returns.date') }}</dt><dd>{{ $record['submitted_at'] }} (UTC)</dd></div></dl>
            <p>{{ __('returns.declaration') }}</p><p>{{ $record['declaration']['contract'] }}</p>
            <a class="button-primary" href="{{ route('returns.download', ['locale' => $locale, 'id' => $record['id']]) }}">{{ __('returns.download') }}</a>
            <p>{{ __('returns.save_notice') }}</p><p>{{ __('returns.logistics_notice') }}</p>
            <x-return-details />
            <a href="{{ route('information', ['locale' => $locale, 'page' => 'retractation']) }}">{{ __('pages.entries.retractation.title') }} →</a>
        </div>
    </main>
</x-layout>
