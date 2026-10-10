<footer class="home-footer">
    <div class="shell footer-discoveries">
        <div class="footer-intro"><a class="footer-brand" href="{{ route('home', ['locale' => app()->getLocale()]) }}"><x-brand-logo loading="lazy" /></a><p>{{ __('home.footer_intro') }}</p></div>
        <nav aria-labelledby="footer-discoveries-heading"><h2 id="footer-discoveries-heading">{{ __('chrome.discoveries_title') }}</h2><ul>
            @foreach(config('storefront.discoveries') as $selection)<li><a href="{{ route('catalogue', ['locale' => app()->getLocale()] + $selection['filters']) }}">{{ __('chrome.discoveries.'.$selection['key']) }}</a></li>@endforeach
        </ul></nav>
    </div>
    <div class="shell home-footer-grid">
        <nav aria-labelledby="footer-categories-heading"><h2 id="footer-categories-heading">{{ __('chrome.categories_title') }}</h2><ul>
            @foreach(config('storefront.categories') as $selection)
                @php($filters = $selection['filters'] + (isset($selection['query_translation']) ? ['q' => __($selection['query_translation'])] : []))
                <li><a href="{{ route('catalogue', ['locale' => app()->getLocale()] + $filters) }}">{{ __('chrome.categories.'.$selection['key']) }}</a></li>
            @endforeach
            <li><a href="{{ route('promotions', ['locale' => app()->getLocale()]) }}">{{ __('promotions.title') }}</a></li>
        </ul></nav>
        <nav aria-labelledby="footer-help-heading"><h2 id="footer-help-heading">{{ __('chrome.help_title') }}</h2><ul>
            @foreach(['conseiller', 'provenance', 'suivi', 'contact', 'a-propos'] as $page)<li><a href="{{ route('information', ['locale' => app()->getLocale(), 'page' => $page]) }}">{{ __('pages.entries.'.$page.'.title') }}</a></li>@endforeach
        </ul><x-contact-actions compact /></nav>
        <nav aria-labelledby="footer-legal-heading"><h2 id="footer-legal-heading">{{ __('chrome.legal_title') }}</h2><ul>
            @foreach(['mentions-legales', 'cgv', 'confidentialite', 'cookies', 'retractation'] as $page)<li><a href="{{ route('information', ['locale' => app()->getLocale(), 'page' => $page]) }}">{{ __('pages.entries.'.$page.'.title') }}</a></li>@endforeach
        <li><a href="{{ route('returns.create', ['locale' => app()->getLocale()]) }}">{{ __('returns.start') }}</a></li></ul></nav>
        <section class="footer-commerce" aria-labelledby="footer-commerce-heading"><h2 id="footer-commerce-heading">{{ __('chrome.commerce_title') }}</h2>
            <span class="footer-transfer-logo"><img src="{{ asset('media/payments/instant-transfer.svg') }}" width="40" height="40" alt="" loading="lazy" decoding="async"><span>{{ __('chrome.instant_transfer') }}</span></span>
            <p>{{ __('commerce.shipping_free') }} {{ __('commerce.delivery_estimate') }}</p>
            <ul class="footer-commerce-links">@foreach(['paiement', 'livraison'] as $page)<li><a href="{{ route('information', ['locale' => app()->getLocale(), 'page' => $page]) }}">{{ __('pages.entries.'.$page.'.title') }} <span aria-hidden="true">→</span></a></li>@endforeach</ul>
        </section>
    </div>
    <div class="shell home-footer-bottom"><span>© {{ date('Y') }} HOMYA</span><span>{{ __('home.footer_intro') }}</span></div>
</footer>
