@props(['src' => null, 'alt' => '', 'eager' => false, 'width' => null, 'height' => null, 'sizes' => '(max-width: 600px) calc(100vw - 32px), (max-width: 1000px) calc((100vw - 64px) / 2), 280px'])
@php($srcset = app(\App\Services\ResponsiveMedia::class)->srcset($src))
<div class="product-media" data-product-media>
    @if ($src)
        <img src="{{ $src }}" @if($srcset) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif alt="{{ $alt }}" @if($width && $height) width="{{ $width }}" height="{{ $height }}" @endif loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" data-product-image>
    @endif
    <span class="image-fallback" data-image-fallback @if($src) hidden @endif>{{ __('catalogue.image_missing') }}</span>
</div>
