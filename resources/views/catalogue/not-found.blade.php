<x-layout :title="__('catalogue.not_found').' — HOMYA'" :catalogue-ready="$catalogueReady" :language-links="$languageLinks">
    <main id="main" class="shell catalogue-page empty-state" tabindex="-1"><h1>{{ __('catalogue.not_found') }}</h1><p>{{ __('catalogue.not_found_help') }}</p><a class="button-primary" href="{{ route('catalogue', ['locale' => app()->getLocale()]) }}">{{ __('catalogue.back_catalogue') }}</a></main>
</x-layout>
