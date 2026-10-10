@php($available = app(\App\Services\Commerce\InventoryAvailability::class)->available($product))
<x-layout :title="$product['name'].' — HOMYA'" :description="$product['description'] ?: $product['name']" :catalogue-ready="$catalogueReady" :language-links="$languageLinks" :search-query="$context['q'] ?? ''" :seo-context="['product' => $seoProduct ?? $product, 'name' => $product['name'], 'available_stock' => $available, 'variant' => $selectedVariant ?? null]">
    <main id="main" class="shell catalogue-page product-page" tabindex="-1">
        <nav class="breadcrumbs" aria-label="{{ __('catalogue.breadcrumbs') }}"><a href="{{ route('home', ['locale' => $locale]) }}">{{ __('home.home') }}</a><span aria-hidden="true">/</span><a href="{{ $catalogueLink }}">{{ __('catalogue.title') }}</a><span aria-hidden="true">/</span><a href="{{ route('catalogue', ['locale' => $locale, 'category' => $product['category']['slug']]) }}">{{ $product['category']['name'] }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $product['name'] }}</span></nav>
        @if($product['description'] || $product['specifications'] || ($product['has_variants'] && collect($product['variants'])->contains(fn(array $variant) => trim($variant['title'] ?? '') !== '')) || $relatedProducts->isNotEmpty())
        <nav class="product-section-navigation" aria-label="{{ __('catalogue.product_navigation') }}">
            @if($product['description'])<a href="#product-description">{{ __('catalogue.product_description') }}</a>@endif
            @if($product['specifications'])<a href="#product-specifications">{{ __('catalogue.specifications') }}</a>@endif
            @if($product['has_variants'] && collect($product['variants'])->contains(fn(array $variant) => trim($variant['title'] ?? '') !== ''))<a href="#product-variants">{{ __('catalogue.variants') }}</a>@endif
            @if($relatedProducts->isNotEmpty())<a href="#related-products">{{ __('catalogue.related_products') }}</a>@endif
        </nav>
        @endif
        <div class="product-detail-grid">
            <section class="gallery" aria-label="{{ __('catalogue.gallery') }}" data-gallery>
                <div class="product-card-visual">
                    <x-product-image :src="$product['images'][0]['local_url'] ?? null" :width="$product['images'][0]['width'] ?? null" :height="$product['images'][0]['height'] ?? null" :alt="$product['name']" sizes="(max-width: 900px) calc(100vw - 32px), 600px" eager />
                    @if($product['promotion'] ?? null)<span class="promotion-badge">−{{ $product['promotion']['percent'] }} %</span>@endif
                </div>
                @if(count($product['images']) > 1)<div class="gallery-arrows"><button type="button" data-gallery-step="-1" aria-label="{{ __('commerce.previous_image') }}" hidden>←</button><button type="button" data-gallery-step="1" aria-label="{{ __('commerce.next_image') }}" hidden>→</button></div>@endif
                @if(count($product['images']) > 1)
                    <div class="gallery-controls">@foreach($product['images'] as $image)<button type="button" data-gallery-src="{{ $image['local_url'] }}" data-gallery-srcset="{{ app(\App\Services\ResponsiveMedia::class)->srcset($image['local_url']) }}" data-gallery-width="{{ $image['width'] }}" data-gallery-height="{{ $image['height'] }}" data-gallery-number="{{ $loop->iteration }}" aria-label="{{ __('catalogue.show_image', ['number' => $loop->iteration]) }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"><span aria-hidden="true">{{ $loop->iteration }}</span><img src="{{ $image['local_url'] }}" @if($thumbnailSet = app(\App\Services\ResponsiveMedia::class)->srcset($image['local_url'])) srcset="{{ $thumbnailSet }}" sizes="80px" @endif @if($image['width'] && $image['height']) width="{{ $image['width'] }}" height="{{ $image['height'] }}" @endif alt="" loading="lazy" data-gallery-thumbnail></button>@endforeach</div>
                @endif
                @if($product['images'])<p class="gallery-caption" data-gallery-caption data-template="{{ __('catalogue.image_count') }}" data-total="{{ count($product['images']) }}" aria-live="polite">{{ __('catalogue.image_count', ['current' => 1, 'total' => count($product['images'])]) }}</p>@endif
            </section>
            <section class="product-summary" aria-labelledby="product-heading">
                <p class="eyebrow">{{ $product['brand'] }}</p><h1 id="product-heading">{{ $product['name'] }}</h1>
                @if($product['reference'])<p class="product-reference">{{ __('catalogue.reference') }} {{ $product['reference'] }}</p>@endif
                <x-product-price :product="$product" :locale="$locale" />
                <p class="product-tax-label">{{ __('commerce.tax_included') }}</p>
                @if($available !== null)<p class="stock-indicator @if($available === 0) stock-empty @endif"><span aria-hidden="true">●</span> {{ __($available > 0 ? 'commerce.in_stock' : 'commerce.out_of_stock') }}</p>@endif
                @if($product['specifications'])<dl class="product-highlights">@foreach(array_slice($product['specifications'], 0, 4) as $spec)<div><dt>{{ $spec['label'] }}</dt><dd>{{ $spec['value'] }}</dd></div>@endforeach</dl>@endif
                <dl class="product-overview"><div><dt>{{ __('catalogue.category') }}</dt><dd><a href="{{ route('catalogue', ['locale' => $locale, 'category' => $product['category']['slug']]) }}">{{ $product['category']['name'] }}</a></dd></div>@if($product['condition'])<div><dt>{{ __('catalogue.condition') }}</dt><dd>{{ __('catalogue.conditions')[$product['condition']] ?? $product['condition'] }}</dd></div>@endif</dl>
                <x-purchase-information :locale="$locale" id="product-purchase-information" open />
                @if(app(\App\Services\Commerce\Cart::class)->eligible($product))
                    <x-commerce-errors />
                    <form id="product-purchase" class="product-cart-form" method="post" action="{{ route('cart.add', ['locale' => $locale]) }}">@csrf
                        <input type="hidden" name="slug" value="{{ $product['slug'] }}">
                        @if($product['has_variants'])<div class="variant-field"><label for="product-variant">{{ __('commerce.variant') }}</label><select id="product-variant" name="variant" required><option value="">{{ __('commerce.choose_variant') }}</option>@foreach(app(\App\Services\Commerce\Cart::class)->options($product) as $optionId => $option)<option value="{{ $optionId }}" @selected(old('variant', $selectedVariant ?? null) === $optionId)>{{ $option['name'] }}</option>@endforeach</select></div>@endif
                        <div class="product-buy-row"><x-quantity-selector id="product-quantity" :value="old('quantity', 1)" :max="config('commerce.max_quantity')" /><x-add-to-cart-button name="intent" value="cart" :disabled="$available === 0" /></div>
                        <button class="button-primary product-buy-now" type="submit" name="intent" value="checkout" @disabled($available === 0)>{{ __(app(\App\Services\Commerce\CheckoutReadiness::class)->ready() ? 'commerce.buy_now' : 'commerce.prepare_order') }}</button>
                    </form>
                @else<p>{{ __('commerce.unavailable_product') }}</p>@endif
                <a class="button-primary" href="{{ $catalogueLink }}">{{ __('catalogue.back_catalogue') }}</a>
            </section>
        </div>
        @if($product['description'])<section id="product-description" class="product-description-section" aria-labelledby="description-heading" tabindex="-1"><h2 id="description-heading">{{ __('catalogue.product_description') }}</h2><p class="product-description">{{ $product['description'] }}</p></section>@endif
        <section id="product-specifications" class="specifications" aria-labelledby="specifications-heading" tabindex="-1"><h2 id="specifications-heading">{{ __('catalogue.specifications') }}</h2>
            @if($product['specifications'])<dl>@foreach($product['specifications'] as $spec)<div><dt>{{ $spec['label'] }}</dt><dd>{{ $spec['value'] }}</dd></div>@endforeach</dl>@else<p>{{ __('catalogue.specifications_missing') }}</p>@endif
        </section>
        @if($product['has_variants'])<section id="product-variants" class="variant-information" aria-labelledby="variants-heading" tabindex="-1"><h2 id="variants-heading">{{ __('catalogue.variants') }}</h2><p>{{ __('catalogue.variants_notice') }}</p><ul>@foreach($product['variants'] as $variant)@if(!empty($variant['title']))<li><a href="{{ route('product', ['locale' => $locale, 'slug' => $product['slug'], 'variant' => 'v'.($loop->index + 1)] + $context) }}#product-purchase" @if(($selectedVariant ?? null) === 'v'.($loop->index + 1)) aria-current="true" @endif>{{ $variant['title'] }}</a>@if(!empty($variant['sku'])) — {{ __('catalogue.reference') }} {{ $variant['sku'] }}@endif</li>@endif @endforeach</ul></section>@endif
        <section id="related-products" class="related-products" aria-labelledby="related-heading" tabindex="-1">
            <div class="section-heading"><div><h2 id="related-heading">{{ __('catalogue.related_products') }}</h2><p>{{ __('catalogue.related_help') }}</p></div><a href="{{ route('catalogue', ['locale' => $locale, 'category' => $product['category']['slug']]) }}">{{ __('catalogue.explore_category') }} <span aria-hidden="true">→</span></a></div>
            @if($relatedProducts->isNotEmpty())<div class="related-product-grid">@foreach($relatedProducts as $related)<x-product-card :product="$related" :locale="$locale" :filters="$context" heading-level="h3" />@endforeach</div>
            @else<p class="related-empty">{{ __($relatedUnavailable ? 'catalogue.related_unavailable' : 'catalogue.related_empty') }}</p>@endif
        </section>
    </main>
</x-layout>
