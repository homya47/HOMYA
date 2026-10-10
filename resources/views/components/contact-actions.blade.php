@props(['compact' => false, 'emailPhoneOnly' => false])
@inject('channels', 'App\Services\ContactChannels')
@php($contactLinks = $channels->links())
@php($contactEmail = $channels->email())
@if($contactLinks || $contactEmail)
    <div class="contact-actions" role="group" aria-label="{{ __('contact.label') }}">
        @if($contactEmail)<p class="contact-number"><a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></p>@endif
        @if($contactLinks && ! $compact && ! $emailPhoneOnly)
        <div class="contact-buttons">
            <a class="contact-button" href="{{ $contactLinks['whatsapp'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-11.8 7L4 20l1.5-4.2A8 8 0 1 1 20 11.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M9 8c0 3 2 5 5 6l1-2-2-1-1 1-2-2 1-1-1-2-1 1Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>
                {{ __('contact.whatsapp') }}
            </a>
            <a class="contact-button" href="{{ $contactLinks['sms'] }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 4h16v13H9l-5 4V4Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8 8h8M8 12h6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                {{ __('contact.sms') }}
            </a>
        </div>
        <p class="contact-number"><a href="{{ $contactLinks['whatsapp'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" aria-label="{{ __('contact.chat_label') }}: {{ $channels->phone() }}">{{ $channels->phone() }}</a></p>
        <p class="contact-help">{{ __('contact.application_help') }}</p>
        @endif
        @if($contactLinks && $emailPhoneOnly)<p class="contact-number"><a href="{{ $contactLinks['whatsapp'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" aria-label="{{ __('contact.chat_label') }}: {{ $channels->phone() }}">{{ $channels->phone() }}</a></p>@endif
        @if($contactLinks && $compact)<p class="contact-number"><a href="{{ $contactLinks['whatsapp'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" aria-label="{{ __('contact.chat_label') }}: {{ $channels->phone() }}">{{ $channels->phone() }}</a></p>@endif
    </div>
@endif
