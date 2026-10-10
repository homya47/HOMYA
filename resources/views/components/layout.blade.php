@props(['title' => null, 'description' => null, 'catalogueReady' => false, 'languageLinks' => [], 'searchQuery' => '', 'seoContext' => []])
@php($seo = app(\App\Services\Seo::class)->page(request(), $title ?? __('home.title'), $description ?? __('home.description'), $seoContext + ['available' => $catalogueReady]))
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="{{ $seo['robots'] }}">
    <title>{{ $seo['title'] }}</title>
    <meta name="description" content="{{ $seo['description'] }}">
    @if($seo['canonical'])<link rel="canonical" href="{{ $seo['canonical'] }}">@endif
    @foreach($seo['alternates'] as $language => $url)
        <link rel="alternate" hreflang="{{ $language }}" href="{{ $url }}">
    @endforeach
    <meta property="og:site_name" content="HOMYA">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta property="og:type" content="{{ $seo['type'] }}">
    @if($seo['canonical'])<meta property="og:url" content="{{ $seo['canonical'] }}">@endif
    @if($seo['image'])<meta property="og:image" content="{{ $seo['image'] }}">@endif
    @if(app(\App\Services\Seo::class)->onPublicOrigin(request()) && config('seo.verification'))<meta name="google-site-verification" content="{{ config('seo.verification') }}">@endif
    @if($seo['json'])<script type="application/ld+json" nonce="{{ request()->attributes->get('security.csp_nonce') }}">{!! $seo['json'] !!}</script>@endif
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=homya1" sizes="16x16 32x32 48x48">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('branding/favicon-32-v1.png') }}">
    <link rel="icon" type="image/png" sizes="96x96" href="{{ asset('branding/favicon-96-v1.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('branding/apple-touch-icon-v1.png') }}">
    <meta name="theme-color" content="#f5c842">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php($cartCount = request()->hasSession() ? app(\App\Services\Commerce\Cart::class)->count(request()->session()) : 0)
<body class="homya-storefront {{ request()->routeIs('home') ? 'home-reference' : '' }}">
    <a class="skip-link" href="#main">{{ __('home.skip') }}</a>
    <div class="home-header-wrap">
    <div class="utility-bar"><div class="shell">
        @if($contactEmail = app(\App\Services\ContactChannels::class)->email())<a class="utility-contact" href="mailto:{{ $contactEmail }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.7"/><path d="m3 6 9 7 9-7" stroke="currentColor" stroke-width="1.7"/></svg>{{ $contactEmail }}</a>@endif
        <nav class="utility-navigation" aria-label="{{ __('chrome.utility_navigation') }}"><ul><li><a href="{{ route('order.tracking', ['locale' => app()->getLocale()]) }}">{{ __('chrome.utility.suivi') }}</a></li>@if(($phone = app(\App\Services\ContactChannels::class)->phone()) && ($phoneLinks = app(\App\Services\ContactChannels::class)->links()))<li><a class="utility-phone" href="{{ $phoneLinks['whatsapp'] }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer" aria-label="{{ __('contact.chat_label') }}: {{ $phone }}">{{ $phone }}</a></li>@endif</ul></nav>
    </div></div>
    <header class="site-header shell">
        <a class="wordmark" href="{{ route('home', ['locale' => app()->getLocale()]) }}" aria-label="HOMYA">
            <x-brand-logo />
        </a>
        <form class="search-preview" action="{{ route('catalogue', ['locale' => app()->getLocale()]) }}" method="get" role="search">
            <label for="catalogue-search">{{ __('home.search') }}</label>
            <div class="search-controls">
                <input id="catalogue-search" name="q" maxlength="120" type="search" value="{{ $searchQuery }}" placeholder="{{ __('home.search_placeholder') }}" aria-describedby="search-help" @disabled(! $catalogueReady)>
                @if ($catalogueReady)<button type="submit" class="search-submit" aria-label="{{ __('catalogue.search_submit') }}">{{ __('catalogue.search_submit') }}</button>@endif
            </div>
            <p id="search-help">{{ $catalogueReady ? __('catalogue.search_help') : __('home.search_unavailable') }}</p>
        </form>
        <div class="header-actions">
            <nav class="storefront-actions" aria-label="{{ __('chrome.services_navigation') }}"><ul>
                <li><a href="{{ route('information', ['locale' => app()->getLocale(), 'page' => 'conseiller']) }}"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 14v-2a8 8 0 0 1 16 0v2M4 13H3v6h4v-6H4Zm16 0h1v6h-4v-6h3Zm0 6c0 2-3 3-6 3" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg><span>{{ __('chrome.services.conseiller') }}</span></a></li>
                <li><a href="{{ route('information', ['locale' => app()->getLocale(), 'page' => 'suivi']) }}"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 5h12v13H2V5Zm12 4h4l4 5v4h-8V9Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="6" cy="18" r="2" fill="var(--brand-yellow)" stroke="currentColor" stroke-width="1.7"/><circle cx="18" cy="18" r="2" fill="var(--brand-yellow)" stroke="currentColor" stroke-width="1.7"/></svg><span>{{ __('chrome.services.suivi') }}</span></a></li>
                <li><a href="{{ route('cart.show', ['locale' => app()->getLocale()]) }}"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m2 3 3 1 3 12h12l2-9H6M8 20h.01M19 20h.01" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg><span>{{ __('chrome.services.panier') }}</span>@if($cartCount > 0)<span class="cart-count" aria-hidden="true">{{ $cartCount }}</span>@endif</a></li>
            </ul></nav>
            <a class="header-cart-mobile" href="{{ route('cart.show', ['locale' => app()->getLocale()]) }}" aria-label="{{ __('commerce.basket_count', ['count' => $cartCount]) }}"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m2 3 3 1 3 12h12l2-9H6M8 20h.01M19 20h.01" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></svg>@if($cartCount > 0)<span class="cart-count" aria-hidden="true">{{ $cartCount }}</span>@endif</a>
            <nav class="languages" aria-label="{{ __('home.language') }}">
                @foreach (config('homya.locales') as $language)
                    <a href="{{ $languageLinks[$language] ?? route('home', ['locale' => $language]) }}" lang="{{ $language }}" hreflang="{{ $language }}" aria-label="{{ __('home.language_names.'.$language) }}" data-language-link @if ($language === app()->getLocale()) aria-current="page" @endif><x-language-flag :locale="$language" /><span>{{ strtoupper($language) }}</span></a>
                @endforeach
            </nav>
            <button class="menu-toggle" type="button" aria-controls="primary-navigation" aria-expanded="false" data-menu-toggle data-open-label="{{ __('home.menu') }}" data-close-label="{{ __('home.close_menu') }}" hidden>
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3 5h14M3 10h14M3 15h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                <span data-menu-label>{{ __('home.menu') }}</span>
            </button>
        </div>
    </header>
    <nav id="primary-navigation" class="primary-navigation shell" aria-label="{{ __('home.navigation') }}">
        <ul>
            <li><a href="{{ route('home', ['locale' => app()->getLocale()]) }}" @if(request()->routeIs('home')) aria-current="page" @endif>{{ __('home.home') }}</a></li>
            <li><a href="{{ route('catalogue', ['locale' => app()->getLocale()]) }}" @if(request()->routeIs('catalogue') && !request('universe')) aria-current="page" @endif>{{ __('catalogue.title') }}</a></li>
            <li><a href="{{ route('promotions', ['locale' => app()->getLocale()]) }}" @if(request()->routeIs('promotions')) aria-current="page" @endif>{{ __('promotions.title') }}</a></li>
            @foreach (__('home.categories') as $slug => $category)
                <li><a href="{{ route('catalogue', ['locale' => app()->getLocale(), 'universe' => $slug]) }}" @if(request()->routeIs('catalogue') && request('universe') === $slug) aria-current="page" @endif>{{ $category['name'] }}</a></li>
            @endforeach
            <li><a href="{{ route('information', ['locale' => app()->getLocale(), 'page' => 'provenance']) }}" @if(request()->routeIs('information') && request()->route('page') === 'provenance') aria-current="page" @endif>{{ __('pages.help') }}</a></li>
            @foreach(['suivi', 'panier'] as $page)<li class="navigation-service"><a href="{{ $page === 'panier' ? route('cart.show', ['locale' => app()->getLocale()]) : route('information', ['locale' => app()->getLocale(), 'page' => $page]) }}">{{ __('pages.entries.'.$page.'.title') }}</a></li>@endforeach
        </ul>
    </nav>
    </div>
    @if($errors->any() && request()->routeIs('home', 'catalogue', 'promotions'))<div class="shell"><x-commerce-errors /></div>@endif
    {{ $slot }}
    <x-storefront-footer />
    <x-whatsapp-chat />
</body>
</html>
