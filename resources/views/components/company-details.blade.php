@inject('channels', 'App\Services\ContactChannels')
@php($contactLinks = $channels->links())
<dl class="company-details">
    @foreach(config('legal.company') as $field => $value)
        <div><dt>{{ __('legal.company_labels.'.$field) }}</dt><dd>{{ $value }}</dd></div>
    @endforeach
    <div><dt>{{ __('legal.company_labels.email') }}</dt><dd>@if($channels->email())<a href="mailto:{{ $channels->email() }}">{{ $channels->email() }}</a>@else{{ __('legal.contact_pending') }}@endif</dd></div>
    <div><dt>{{ __('legal.company_labels.phone') }}</dt><dd>@if($channels->phone() && $contactLinks)<a href="{{ $contactLinks['whatsapp'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" aria-label="{{ __('contact.chat_label') }}: {{ $channels->phone() }}">{{ $channels->phone() }}</a>@else{{ __('legal.contact_pending') }}@endif</dd></div>
</dl>
