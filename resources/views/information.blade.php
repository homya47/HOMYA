<x-layout :title="$content['title'].' — HOMYA'" :description="$content['intro']" :catalogue-ready="$catalogueReady" :language-links="$languageLinks" :seo-context="['pending' => $content['pending'], 'name' => $content['title']]">
    @php($isLegal = $content['legal'] ?? false)
    @php($isCommerceInfo = $isLegal || in_array($page, ['livraison', 'paiement', 'contact'], true))
    <main id="main" class="shell information-page {{ $isLegal ? 'information-page--legal' : '' }}" tabindex="-1">
        <nav class="breadcrumbs" aria-label="{{ __('catalogue.breadcrumbs') }}"><a href="{{ route('home', ['locale' => $locale]) }}">{{ __('home.home') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $content['title'] }}</span></nav>
        @if($page === 'conseiller')<x-advice-panel :locale="$locale" :standalone="true" heading-id="information-advice-heading" />@endif
        <div class="information-layout">
            <article>
                @unless($page === 'conseiller')<header class="information-heading"><p class="eyebrow">HOMYA · {{ __('pages.information') }}</p><h1>{{ $content['title'] }}</h1><p>{{ $content['intro'] }}</p></header>@endunless
                @if($page === 'a-propos')<img class="information-image" src="{{ asset('media/home/interieur-display.webp') }}" width="1600" height="600" alt="{{ __('home.hero_alt') }}" decoding="async">@endif
                @if($isLegal)
                    @if($page === 'retractation')<x-return-overview :locale="$locale" />@endif
                    <x-legal-document :content="$content" :page="$page" />
                @else
                    <section class="information-copy" aria-label="{{ $content['title'] }}">@foreach($content['paragraphs'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach</section>
                @endif
                @if($page === 'contact')<x-company-details /><x-contact-actions email-phone-only />@endif
                @if(in_array($page, ['provenance', 'conseiller'], true))
                    <section id="questions" class="information-faq" aria-labelledby="faq-heading" tabindex="-1"><h2 id="faq-heading">{{ __('pages.questions') }}</h2>@foreach(__('pages.faq') as $item)<details><summary>{{ $item['question'] }}</summary><p>{{ $item['answer'] }}</p></details>@endforeach</section>
                @endif
                <div class="information-actions"><a class="button-primary" href="{{ route('catalogue', ['locale' => $locale]) }}">{{ __('home.all_catalogue') }} <span aria-hidden="true">→</span></a><a href="{{ route('home', ['locale' => $locale]) }}#universes">{{ __('home.explore') }}</a></div>
            </article>
            <aside class="information-sidebar" aria-label="{{ $isCommerceInfo ? __('legal.purchase_information') : __('pages.help') }}"><h2>{{ $isCommerceInfo ? __('legal.purchase_information') : __('pages.help') }}</h2><ul>@foreach($isCommerceInfo ? ['livraison', 'paiement', 'retractation', 'cgv', 'mentions-legales', 'confidentialite', 'cookies', 'contact'] : ['a-propos', 'provenance', 'conseiller', 'contact', 'livraison', 'retractation'] as $destination)<li><a href="{{ route('information', ['locale' => $locale, 'page' => $destination]) }}" @if($page === $destination) aria-current="page" @endif>{{ __('pages.entries.'.$destination.'.title') }}<span aria-hidden="true">→</span></a></li>@endforeach</ul></aside>
        </div>
    </main>
</x-layout>
