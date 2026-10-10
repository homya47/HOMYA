<x-layout :title="$catalogueIntro['title'].' — HOMYA'" :description="$catalogueIntro['description']" :catalogue-ready="$catalogueReady" :language-links="$languageLinks" :search-query="$formValues['q'] ?? ''" :seo-context="['filters' => $filters, 'name' => $catalogueIntro['title'], 'available' => !$invalid && $products->isNotEmpty()]">
    <main id="main" class="shell catalogue-page" tabindex="-1">
        <nav class="breadcrumbs" aria-label="{{ __('catalogue.breadcrumbs') }}"><a href="{{ route('home', ['locale' => $locale]) }}">{{ __('home.home') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ __('catalogue.title') }}</span></nav>
        <div class="catalogue-intro">
            <div class="catalogue-intro-copy page-heading"><p class="eyebrow">HOMYA</p><h1>{{ $catalogueIntro['title'] }}</h1><p>{{ $catalogueIntro['description'] }}</p><p class="catalogue-total">{{ trans_choice($catalogueIntro['universe'] ? 'catalogue.department_count' : 'catalogue.total_count', $catalogueIntro['count'], ['count' => $catalogueIntro['count']]) }}</p></div>
            @if($catalogueIntro['image'])<img class="catalogue-intro-image" src="{{ $catalogueIntro['image']['local_url'] }}" width="{{ $catalogueIntro['image']['width'] }}" height="{{ $catalogueIntro['image']['height'] }}" alt="" decoding="async">@endif
        </div>
        @if($browseCategories->isNotEmpty())
            <nav class="category-navigation" aria-label="{{ __('catalogue.browse_categories') }}">
                <div class="category-navigation-heading"><h2>{{ __('catalogue.browse_categories') }}</h2><a href="#filter-category" data-open-category-filter>{{ __('catalogue.all_categories') }}</a></div>
                <ul>@foreach($browseCategories as $category)<li><a class="category-entry" href="{{ $category['url'] }}" @if(($filters['category'] ?? '') === $category['slug']) aria-current="page" @endif><span>{{ $category['name'] }}</span><small>{{ trans_choice('catalogue.category_count', $category['count'], ['count' => $category['count']]) }}</small><span aria-hidden="true">→</span></a></li>@endforeach</ul>
            </nav>
        @endif
        @if ($invalid)<div id="filter-errors" class="form-error" role="alert" tabindex="-1"><p>{{ __('catalogue.invalid_filters') }}</p><a href="{{ route('catalogue', ['locale' => $locale]) }}">{{ __('catalogue.reset') }}</a></div>@endif
        <div class="catalogue-layout">
            <details class="filter-panel" data-filter-panel open>
                <summary>{{ __('catalogue.filters') }}</summary>
                <form action="{{ route('catalogue', ['locale' => $locale]) }}" method="get">
                    <label for="filter-query">{{ __('home.search') }}</label><input id="filter-query" type="search" name="q" @if(in_array('q', $errorFields, true)) aria-invalid="true" aria-describedby="filter-errors" @endif maxlength="120" value="{{ $formValues['q'] ?? '' }}">
                    <label for="filter-universe">{{ __('catalogue.universe') }}</label>
                    <select id="filter-universe" name="universe" @if(in_array('universe', $errorFields, true)) aria-invalid="true" aria-describedby="filter-errors" @endif><option value="">{{ __('catalogue.all') }}</option>
                        @foreach(__('home.categories') as $key => $category)<option value="{{ $key }}" @selected(($formValues['universe'] ?? '') === $key)>{{ $category['name'] }}</option>@endforeach
                        <option value="other" @selected(($formValues['universe'] ?? '') === 'other')>{{ __('catalogue.other') }}</option>
                    </select>
                    <label for="filter-category">{{ __('catalogue.category') }}</label>
                    <select id="filter-category" name="category" aria-describedby="category-help{{ in_array('category', $errorFields, true) ? ' filter-errors' : '' }}" @if(in_array('category', $errorFields, true)) aria-invalid="true" @endif><option value="">{{ __('catalogue.all') }}</option>@foreach($categories as $category)<option value="{{ $category['slug'] }}" @selected(($formValues['category'] ?? '') === $category['slug'])>{{ $category['name'] }}</option>@endforeach</select>
                    <p id="category-help" class="filter-help">{{ __('catalogue.category_help') }}</p>
                    <p id="category-reset" class="filter-help" role="status" @if(!$categoryReset) hidden @endif>{{ __('catalogue.category_reset') }}</p>
                    <noscript><p class="filter-help">{{ __('catalogue.category_nojs') }}</p></noscript>
                    <template id="category-options">@foreach($categoryOptions as $category)<option value="{{ $category['slug'] }}" data-universes="{{ implode(',', $category['universes']) }}">{{ $category['name'] }}</option>@endforeach</template>
                    <label for="filter-brand">{{ __('catalogue.brand') }}</label>
                    <select id="filter-brand" name="brand" @if(in_array('brand', $errorFields, true)) aria-invalid="true" aria-describedby="filter-errors" @endif><option value="">{{ __('catalogue.all') }}</option>@foreach($brands as $brand)<option @selected(($formValues['brand'] ?? '') === $brand)>{{ $brand }}</option>@endforeach</select>
                    @if($conditions->isNotEmpty())<label for="filter-condition">{{ __('catalogue.condition') }}</label><select id="filter-condition" name="condition" @if(in_array('condition', $errorFields, true)) aria-invalid="true" aria-describedby="filter-errors" @endif><option value="">{{ __('catalogue.all') }}</option>@foreach($conditions as $condition)<option value="{{ $condition }}" @selected(($formValues['condition'] ?? '') === $condition)>{{ __('catalogue.conditions')[$condition] ?? $condition }}</option>@endforeach</select>@endif
                    <fieldset><legend>{{ __('catalogue.price_range') }}</legend><div class="price-inputs"><div><label for="filter-min">{{ __('catalogue.min') }}</label><input id="filter-min" name="min" @if(in_array('min', $errorFields, true)) aria-invalid="true" aria-describedby="filter-errors" @endif type="text" inputmode="decimal" maxlength="11" value="{{ $formValues['min'] ?? '' }}"></div><div><label for="filter-max">{{ __('catalogue.max') }}</label><input id="filter-max" name="max" @if(in_array('max', $errorFields, true)) aria-invalid="true" aria-describedby="filter-errors" @endif type="text" inputmode="decimal" maxlength="11" value="{{ $formValues['max'] ?? '' }}"></div></div></fieldset>
                    <label for="filter-sort">{{ __('catalogue.sort') }}</label><select id="filter-sort" name="sort" @if(in_array('sort', $errorFields, true)) aria-invalid="true" aria-describedby="filter-errors" @endif><option value="name" @selected(($formValues['sort'] ?? 'name') === 'name')>{{ __('catalogue.sort_name') }}</option><option value="price_asc" @selected(($formValues['sort'] ?? '') === 'price_asc')>{{ __('catalogue.sort_price_asc') }}</option><option value="price_desc" @selected(($formValues['sort'] ?? '') === 'price_desc')>{{ __('catalogue.sort_price_desc') }}</option></select>
                    <button class="button-primary" type="submit">{{ __('catalogue.apply') }}</button><a class="reset-link" href="{{ route('catalogue', ['locale' => $locale]) }}">{{ __('catalogue.reset') }}</a>
                </form>
            </details>
            <section aria-labelledby="results-heading">
                <div class="results-heading"><h2 id="results-heading" tabindex="-1">{{ trans_choice('catalogue.results', $products->total(), ['count' => $products->total()]) }}</h2><span>{{ __('catalogue.page', ['current' => $products->currentPage(), 'total' => $products->lastPage()]) }}</span></div>
                @if($activeFilters)
                    <div class="active-filters"><p>{{ __('catalogue.active_filters') }}</p><ul>@foreach($activeFilters as $filter)<li><a href="{{ $filter['url'] }}" aria-label="{{ __('catalogue.remove_filter', ['filter' => $filter['text']]) }}"><span>{{ $filter['text'] }}</span><span aria-hidden="true">×</span></a></li>@endforeach</ul></div>
                @endif
                @if ($products->isEmpty())
                    <div class="empty-state"><h3>{{ __($catalogueReady ? 'catalogue.empty' : 'catalogue.unavailable') }}</h3><p>{{ __($catalogueReady ? 'catalogue.empty_help' : 'catalogue.unavailable_help') }}</p><a class="button-primary" href="{{ route($catalogueReady ? 'catalogue' : 'home', ['locale' => $locale]) }}">{{ __($catalogueReady ? 'catalogue.reset' : 'home.home') }}</a></div>
                @else
                    <div class="product-grid">@foreach($products as $product)<x-product-card :product="$product" :locale="$locale" :filters="$filters" />@endforeach</div>
                    <x-catalogue-pagination :products="$products" :filters="$filters" :locale="$locale" />
                @endif
            </section>
        </div>
    </main>
</x-layout>
