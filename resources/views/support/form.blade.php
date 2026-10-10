<x-layout :title="__('support.title').' — HOMYA'" :catalogue-ready="true">
    <main id="main" class="shell commerce-page support-page" tabindex="-1">
        <header class="support-heading">
            <h1>{{ __('support.title') }}</h1>
            <p>{{ __('support.intro') }}</p>
        </header>
        <x-commerce-errors />
        <div class="support-layout">
            <form class="support-card support-form" method="post" action="{{ route('support.create', ['locale' => $locale]) }}" aria-labelledby="support-new-heading">
                @csrf
                <input type="hidden" name="nonce" value="{{ session('support.nonce') }}">
                <header class="support-card-heading">
                    <span class="support-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M4 5h16v12H9l-5 4V5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M8 9h8M8 13h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
                    <div><h2 id="support-new-heading">{{ __('support.new') }}</h2><p>{{ __('support.new_help') }}</p></div>
                </header>
                <div class="support-contact-fields">
                    <div class="support-field">
                        <label for="support-name">{{ __('support.name') }}</label>
                        <input id="support-name" name="name" required maxlength="150" autocomplete="name" @error('name') aria-invalid="true" aria-describedby="support-name-error" @enderror>
                        @error('name')<p class="support-field-error" id="support-name-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="support-field">
                        <label for="support-email">{{ __('support.email') }}</label>
                        <input id="support-email" name="email" type="email" maxlength="254" autocomplete="email" @error('email') aria-invalid="true" aria-describedby="support-email-error" @enderror>
                        @error('email')<p class="support-field-error" id="support-email-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="support-field">
                    <label for="support-message">{{ __('support.message') }}</label>
                    <textarea id="support-message" name="body" required maxlength="5000" rows="5" @error('body') aria-invalid="true" aria-describedby="support-message-error" @enderror></textarea>
                    @error('body')<p class="support-field-error" id="support-message-error">{{ $message }}</p>@enderror
                </div>
                <button class="button-primary support-send" type="submit">{{ __('support.send') }} <span aria-hidden="true">→</span></button>
                <p class="support-privacy">{{ __('support.privacy') }} <a href="{{ route('information', ['locale' => $locale, 'page' => 'confidentialite']) }}">{{ __('support.privacy_link') }}</a></p>
            </form>
            <form class="support-card support-card--resume support-form" method="post" action="{{ route('support.resume', ['locale' => $locale]) }}" aria-labelledby="support-resume-heading">
                @csrf
                <header class="support-card-heading">
                    <span class="support-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M7 11V8a5 5 0 0 1 10 0v3M5 11h14v10H5V11Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12 15v2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg></span>
                    <div><h2 id="support-resume-heading">{{ __('support.resume') }}</h2><p id="support-resume-help">{{ __('support.resume_help') }}</p></div>
                </header>
                <div class="support-field">
                    <label for="support-reference">{{ __('support.reference') }}</label>
                    <input id="support-reference" name="id" required maxlength="36" autocomplete="off" spellcheck="false" aria-describedby="support-resume-help{{ $errors->has('id') ? ' support-reference-error' : '' }}" @error('id') aria-invalid="true" @enderror>
                    @error('id')<p class="support-field-error" id="support-reference-error">{{ $message }}</p>@enderror
                </div>
                <div class="support-field">
                    <label for="support-code">{{ __('support.code') }}</label>
                    <input id="support-code" name="code" type="password" required maxlength="80" autocomplete="off" @error('code') aria-invalid="true" aria-describedby="support-code-error" @enderror>
                    @error('code')<p class="support-field-error" id="support-code-error">{{ $message }}</p>@enderror
                </div>
                <button class="button-secondary support-open" type="submit">{{ __('support.open') }} <span aria-hidden="true">→</span></button>
            </form>
        </div>
    </main>
</x-layout>
