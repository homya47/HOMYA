@props(['floating' => true])
@inject('channels', 'App\Services\ContactChannels')
@php($chatLinks = $channels->links())
@if($chatLinks)
    <a class="{{ $floating ? 'whatsapp-chat' : 'button-primary whatsapp-chat-inline' }}" href="{{ $chatLinks['whatsapp'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" aria-label="{{ __('contact.chat_label') }}" title="{{ __('contact.chat_help') }}">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-11.8 7L4 20l1.5-4.2A8 8 0 1 1 20 11.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M9 8c0 3 2 5 5 6l1-2-2-1-1 1-2-2 1-1-1-2-1 1Z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/></svg>
        <span>{{ __('contact.chat_action') }}</span>
    </a>
@endif
