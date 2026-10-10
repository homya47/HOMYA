@props(['content', 'page'])

<div class="legal-document">
    <p class="legal-review"><time datetime="{{ config('legal.reviewed_on') }}">{{ __('legal.reviewed') }}</time></p>
    <nav class="legal-toc" aria-labelledby="legal-toc-heading">
        <h2 id="legal-toc-heading">{{ __('legal.toc') }}</h2>
        <ul>@foreach($content['sections'] as $section)<li><a href="#legal-{{ $section['id'] }}"><span aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $section['title'] }}</a></li>@endforeach</ul>
    </nav>
    @foreach($content['sections'] as $section)
        <section class="legal-section" aria-labelledby="legal-{{ $section['id'] }}">
            <h2 id="legal-{{ $section['id'] }}" tabindex="-1"><span class="legal-section-number" aria-hidden="true">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $section['title'] }}</h2>
            @foreach($section['paragraphs'] ?? [] as $paragraph)
                {{-- Requested presentation cleanup; retain the underlying terms and their stored fingerprints. --}}
                @unless($paragraph === __('commerce.deferred_legal_notice') || ($page === 'cgv' && $section['id'] === 'commande' && in_array($loop->index, [2, 3], true)))
                    <p>{{ $paragraph }}</p>
                @endunless
            @endforeach
            @if($section['company_details'] ?? false)<x-company-details />@endif
            @if($section['return_details'] ?? false)<x-return-details />@endif
            @if($section['mediator_details'] ?? false)<x-mediator-details />@endif
            @if(isset($section['items']))<ul>@foreach($section['items'] as $item)<li>{{ $item }}</li>@endforeach</ul>@endif
        </section>
    @endforeach
    @if($page === 'cookies')
        <section class="legal-section" aria-labelledby="legal-cookie-inventory">
            <h2 id="legal-cookie-inventory">{{ __('legal.cookie_inventory') }}</h2>
            <dl class="legal-cookie-inventory">
                @foreach([config('session.cookie') => 'session_cookie', 'XSRF-TOKEN' => 'csrf_cookie'] as $name => $purpose)
                    <div><dt><code>{{ $name }}</code></dt><dd>{{ __('legal.'.$purpose) }} {{ config('session.expire_on_close') ? __('legal.cookie_session_duration') : __('legal.cookie_duration', ['minutes' => config('session.lifetime')]) }}</dd></div>
                @endforeach
            </dl>
        </section>
    @endif
    <section class="legal-section legal-sources" aria-labelledby="legal-sources-heading">
        <h2 id="legal-sources-heading">{{ __('legal.sources_heading') }}</h2>
        <details><summary>{{ __('returns.sources') }}</summary><ul>@foreach($content['sources'] as $source)<li><a href="{{ config('legal.sources.'.$source) }}" target="_blank" rel="noopener noreferrer" referrerpolicy="no-referrer">{{ __('legal.source_labels.'.$source) }} <span aria-hidden="true">↗</span></a></li>@endforeach</ul></details>
    </section>
</div>
