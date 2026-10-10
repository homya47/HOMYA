<!doctype html><html lang="{{ $locale }}"><meta charset="utf-8"><title>HOMYA — {{ __('returns.received') }}</title><body>
<h1>HOMYA — {{ __('returns.received') }}</h1>
<p>{{ __('returns.reference') }} : {{ $record['id'] }}</p><p>{{ __('returns.date') }} : {{ $record['submitted_at'] }} (UTC)</p>
<p>{{ __('returns.declaration') }}</p>
<dl>@foreach(['name', 'contract', 'email', 'notes'] as $field)@if($record['declaration'][$field] ?? null)<dt>{{ __('returns.'.$field) }}</dt><dd>{{ $record['declaration'][$field] }}</dd>@endif @endforeach</dl>
<p>{{ __('returns.method') }} : {{ __('returns.download_method') }}</p>
<p>{{ __('returns.receipt_notice') }}</p><p>{{ __('returns.logistics_notice') }}</p>
<p>HOMYA — {{ config('legal.company.address') }}</p>
</body></html>
