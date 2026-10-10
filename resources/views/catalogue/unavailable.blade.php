<x-layout :title="__('catalogue.unavailable').' — HOMYA'" :catalogue-ready="false" :language-links="$languageLinks">
    <main id="main" class="shell catalogue-page" tabindex="-1">
        <div class="empty-state"><h1>{{ __('catalogue.unavailable') }}</h1><p>{{ __($translationFailure ? ($slug ? 'catalogue.translation_unavailable' : 'catalogue.translation_catalogue_unavailable') : 'catalogue.source_unavailable') }}</p>
            @if ($translationFailure)
            <a class="button-primary" href="{{ $slug ? route('product', ['locale' => 'fr', 'slug' => $slug]) : route('catalogue', ['locale' => 'fr']) }}">{{ __('catalogue.view_french') }}</a>
            @else
            <a class="button-primary" href="{{ route('home', ['locale' => $locale]) }}">{{ __('catalogue.back_home') }}</a>
            @endif
        </div>
    </main>
</x-layout>
