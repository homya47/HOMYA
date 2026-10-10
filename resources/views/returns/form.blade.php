<x-layout :title="__('returns.start').' — HOMYA'" :description="__('returns.intro')" :catalogue-ready="true">
    <main id="main" class="shell returns-page" tabindex="-1">
        <a class="commerce-back" href="{{ route('information', ['locale' => $locale, 'page' => 'retractation']) }}">← {{ __('pages.entries.retractation.title') }}</a>
        <header class="information-heading"><p class="eyebrow">HOMYA · {{ __('returns.step', ['step' => $review ? 2 : 1]) }}</p><h1>{{ __($review ? 'returns.review' : 'returns.start') }}</h1><p>{{ __('returns.intro') }}</p></header>
        <x-commerce-errors />
        <div class="return-form-card">
        @if($review)
            <p>{{ __('returns.declaration') }}</p>
            <dl class="company-details">
                <div><dt>{{ __('returns.name') }}</dt><dd>{{ $data['name'] }}</dd></div>
                <div><dt>{{ __('returns.contract') }}</dt><dd>{{ $data['contract'] }}</dd></div>
                @if($data['email'] ?? null)<div><dt>{{ __('returns.email') }}</dt><dd>{{ $data['email'] }}</dd></div>@endif
                <div><dt>{{ __('returns.method') }}</dt><dd>{{ __('returns.download_method') }}</dd></div>
                @if($data['logistics'] ?? null)<div><dt>{{ __('returns.logistics') }}</dt><dd>{{ __('returns.methods.'.$data['logistics']) }}</dd></div>@endif
                @if($data['notes'] ?? null)<div><dt>{{ __('returns.notes') }}</dt><dd>{{ $data['notes'] }}</dd></div>@endif
            </dl>
            <form method="post" action="{{ route('returns.store', ['locale' => $locale]) }}">@csrf
                <input type="hidden" name="nonce" value="{{ session('returns.nonce') }}">
                <p>{{ __('returns.receipt_notice') }}</p>
                <button class="button-primary" type="submit">{{ __('returns.confirm') }}</button>
            </form>
            <a class="commerce-back" href="{{ route('returns.create', ['locale' => $locale]) }}">{{ __('returns.edit') }}</a>
        @else
            <form class="return-form" method="post" action="{{ route('returns.review', ['locale' => $locale]) }}">@csrf
                <p>{{ __('returns.required') }}</p>
                <label for="return-name">{{ __('returns.name') }} *</label><input id="return-name" name="name" required maxlength="150" autocomplete="name" value="{{ old('name', $data['name'] ?? null) }}">
                <label for="return-contract">{{ __('returns.contract') }} *</label><textarea id="return-contract" name="contract" required maxlength="500" rows="3" aria-describedby="return-contract-help">{{ old('contract', $data['contract'] ?? null) }}</textarea><p id="return-contract-help" class="field-help">{{ __('returns.contract_help') }}</p>
                <label for="return-email">{{ __('returns.email') }}</label><input id="return-email" type="email" name="email" maxlength="254" autocomplete="email" value="{{ old('email', $data['email'] ?? null) }}">
                <label for="return-method">{{ __('returns.method') }} *</label><select id="return-method" name="method" required><option value="download">{{ __('returns.download_method') }}</option></select>
                <label for="return-logistics">{{ __('returns.logistics') }}</label><select id="return-logistics" name="logistics">@foreach(__('returns.methods') as $key => $label)<option value="{{ $key }}" @selected(old('logistics', $data['logistics'] ?? 'unknown') === $key)>{{ $label }}</option>@endforeach</select>
                <label for="return-notes">{{ __('returns.notes') }}</label><textarea id="return-notes" name="notes" maxlength="1000" rows="3">{{ old('notes', $data['notes'] ?? null) }}</textarea>
                <p>{{ __('returns.logistics_notice') }}</p><p>{{ __('returns.privacy') }} <a href="{{ route('information', ['locale' => $locale, 'page' => 'confidentialite']) }}">{{ __('pages.entries.confidentialite.title') }}</a></p>
                <button class="button-primary" type="submit">{{ __('returns.review') }} →</button>
            </form>
        @endif
        </div>
    </main>
</x-layout>
