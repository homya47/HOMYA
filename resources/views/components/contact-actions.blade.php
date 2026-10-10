@props(['compact' => false])
@inject('channels', 'App\Services\ContactChannels')
@php($contactLinks = $channels->links())
@if($contactLinks)
    <div class="contact-actions" role="group" aria-label="{{ __('contact.label') }}">
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
        <p class="contact-number">{{ $channels->phone() }}</p>
        @unless($compact)<p class="contact-help">{{ __('contact.application_help') }}</p>@endunless
    </div>
@endif
