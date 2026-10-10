@props(['products', 'filters' => [], 'locale', 'routeName' => 'catalogue'])
@if($products->lastPage() > 1)
    @php
        $current = $products->currentPage();
        $last = $products->lastPage();
        $pages = array_values(array_unique(array_merge([1], range(max(1, $current - 2), min($last, $current + 2)), [$last])));
        sort($pages);
        $previous = 0;
    @endphp
    <nav class="pagination" aria-label="{{ __('catalogue.pagination') }}">
        <ul class="pagination-links">
            <li>@if($products->onFirstPage())<span aria-disabled="true">← {{ __('catalogue.previous') }}</span>@else<a href="{{ $products->previousPageUrl() }}#results-heading" rel="prev">← {{ __('catalogue.previous') }}</a>@endif</li>
            @foreach($pages as $number)
                @if($number - $previous > 1)<li class="pagination-gap"><span aria-hidden="true">…</span></li>@endif
                <li>@if($number === $current)<span aria-current="page" aria-label="{{ __('catalogue.page', ['current' => $current, 'total' => $last]) }}">{{ $number }}</span>@else<a href="{{ $products->url($number) }}#results-heading" aria-label="{{ __('catalogue.go_page', ['number' => $number]) }}">{{ $number }}</a>@endif</li>
                @php $previous = $number; @endphp
            @endforeach
            <li>@if($products->hasMorePages())<a href="{{ $products->nextPageUrl() }}#results-heading" rel="next">{{ __('catalogue.next') }} →</a>@else<span aria-disabled="true">{{ __('catalogue.next') }} →</span>@endif</li>
        </ul>
        <form class="page-jump" method="get" action="{{ route($routeName, ['locale' => $locale]) }}#results-heading">
            @foreach($filters as $key => $value)@if($key !== 'page')<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
            <label for="jump-page">{{ __('catalogue.jump_page') }}</label><input id="jump-page" name="page" type="number" inputmode="numeric" min="1" max="{{ $last }}" value="{{ $current }}" required><button type="submit">{{ __('catalogue.go') }}</button>
        </form>
    </nav>
@endif
