<x-layout :title="__('promotions.title').' — HOMYA'" :description="__('promotions.description')" :catalogue-ready="$catalogueReady" :language-links="$languageLinks" :search-query="$filters['q'] ?? ''" :seo-context="['filters' => $filters, 'available' => !$invalid && $products->isNotEmpty()]">
    <main id="main" class="shell catalogue-page promotions-page" tabindex="-1">
        <nav class="breadcrumbs" aria-label="{{ __('catalogue.breadcrumbs') }}"><a href="{{ route('home', ['locale' => $locale]) }}">{{ __('home.home') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ __('promotions.title') }}</span></nav>
        <div class="page-heading"><p class="eyebrow">HOMYA</p><h1>{{ __('promotions.title') }}</h1><p>{{ __('promotions.description') }}</p></div>
        <div class="promotion-toolbar">
            @if(isset($filters['discount']))<a class="reset-link" href="{{ route('promotions', ['locale' => $locale] + array_diff_key($filters, ['discount' => true, 'page' => true])) }}#results-heading">{{ __('promotions.all') }}</a>@endif
            <form class="promotion-search" method="get" action="{{ route('promotions', ['locale' => $locale]) }}#results-heading">
                @if(isset($filters['discount']))<input type="hidden" name="discount" value="{{ $filters['discount'] }}">@endif
                <label for="promotion-query">{{ __('home.search') }}</label><div><input id="promotion-query" type="search" name="q" maxlength="120" value="{{ $filters['q'] ?? '' }}" @if($queryInvalid) aria-invalid="true" aria-describedby="filter-errors" @endif><button class="button-primary" type="submit">{{ __('catalogue.search_submit') }}</button></div>
            </form>
        </div>
        @if($invalid)<div id="filter-errors" class="form-error" role="alert" tabindex="-1"><p>{{ __('promotions.invalid_filters') }}</p><a href="{{ route('promotions', ['locale' => $locale]) }}">{{ __('catalogue.reset') }}</a></div>@endif
        <section aria-labelledby="results-heading">
            <div class="results-heading"><h2 id="results-heading" tabindex="-1">{{ trans_choice('catalogue.results', $products->total(), ['count' => $products->total()]) }}</h2><span>{{ __('catalogue.page', ['current' => $products->currentPage(), 'total' => $products->lastPage()]) }}</span></div>
            @if($products->isEmpty())<div class="empty-state"><h3>{{ __('promotions.empty') }}</h3><p>{{ __('promotions.empty_help') }}</p><a class="button-primary" href="{{ route('promotions', ['locale' => $locale]) }}">{{ __('catalogue.reset') }}</a><a class="reset-link" href="{{ route('catalogue', ['locale' => $locale]) }}">{{ __('catalogue.explore') }}</a></div>
            @else
                <div class="product-grid">@foreach($products as $product)<x-product-card :product="$product" :locale="$locale" />@endforeach</div>
                <x-catalogue-pagination :products="$products" :filters="$filters" :locale="$locale" route-name="promotions" />
            @endif
        </section>
    </main>
</x-layout>
