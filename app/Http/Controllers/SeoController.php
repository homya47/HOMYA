<?php

namespace App\Http\Controllers;

use App\Services\Catalogue;
use App\Services\Seo;
use Illuminate\Http\Request;
use XMLWriter;

final class SeoController extends Controller
{
    public function robots(Request $request, Seo $seo)
    {
        $body = $seo->onPublicOrigin($request)
            ? "User-agent: *\nAllow: /\n\nSitemap: ".$seo->origin()."/sitemap.xml\n"
            : "User-agent: *\nDisallow: /\n";

        // Private URLs are protected by access control and noindex headers. Crawling
        // remains possible so Google can observe those headers, not a robots block.
        return response($body)->header('Content-Type', 'text/plain; charset=UTF-8')->header('Cache-Control', 'no-cache');
    }

    public function index(Request $request, Catalogue $catalogue, Seo $seo)
    {
        abort_unless($seo->onPublicOrigin($request), 404);
        app()->setLocale('fr');
        $products = $catalogue->products();
        abort_if($catalogue->sourceUnavailable() || $catalogue->translationUnavailable() || $products->isEmpty(), 503);
        $xml = $this->xml('sitemapindex');
        foreach (config('homya.locales') as $locale) {
            $xml->startElement('sitemap');
            $xml->writeElement('loc', $seo->url('seo.pages', ['locale' => $locale]));
            $xml->endElement();
            for ($chunk = 1; $chunk <= (int) ceil($products->count() / config('seo.sitemap_chunk_size')); $chunk++) {
                $xml->startElement('sitemap');
                $xml->writeElement('loc', $seo->url('seo.products', compact('locale', 'chunk')));
                $xml->endElement();
            }
        }

        return $this->response($xml);
    }

    public function pages(Request $request, Catalogue $catalogue, Seo $seo, string $locale)
    {
        abort_unless($seo->onPublicOrigin($request), 404);
        app()->setLocale($locale);
        $products = $catalogue->products();
        abort_if($catalogue->sourceUnavailable() || $catalogue->translationUnavailable() || $products->isEmpty(), 503);
        $urls = [$seo->url('home', compact('locale')), $seo->url('catalogue', compact('locale'))];
        if ($products->contains(fn ($product) => $product['promotion'] !== null)) {
            $urls[] = $seo->url('promotions', compact('locale'));
        }
        foreach ($products->pluck('universe')->filter()->unique() as $universe) {
            $urls[] = $seo->url('catalogue', compact('locale', 'universe'));
        }
        foreach ($products->pluck('category.slug')->filter()->unique() as $category) {
            $urls[] = $seo->url('catalogue', compact('locale', 'category'));
        }
        // These editorial pages have confirmed content. Draft/service pages stay out.
        foreach (['a-propos', 'provenance', 'conseiller'] as $page) {
            $urls[] = $seo->url('information', compact('locale', 'page'));
        }

        return $this->urls($urls);
    }

    public function products(Request $request, Catalogue $catalogue, Seo $seo, string $locale, string $chunk)
    {
        abort_unless($seo->onPublicOrigin($request), 404);
        app()->setLocale($locale);
        $products = $catalogue->products();
        abort_if($catalogue->sourceUnavailable() || $catalogue->translationUnavailable() || $products->isEmpty(), 503);
        $items = $products->forPage((int) $chunk, config('seo.sitemap_chunk_size'));
        abort_if($items->isEmpty(), 404);

        return $this->urls($items->map(fn ($product) => $seo->url('product', ['locale' => $locale, 'slug' => $product['slug']]))->all());
    }

    private function xml(string $root): XMLWriter
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNS(null, $root, 'http://www.sitemaps.org/schemas/sitemap/0.9');

        return $xml;
    }

    private function urls(array $urls)
    {
        $xml = $this->xml('urlset');
        foreach (array_unique($urls) as $url) {
            $xml->startElement('url');
            $xml->writeElement('loc', $url);
            $xml->endElement();
        }

        return $this->response($xml);
    }

    private function response(XMLWriter $xml)
    {
        $xml->endElement();
        $xml->endDocument();

        return response($xml->outputMemory())->header('Content-Type', 'application/xml; charset=UTF-8')->header('Cache-Control', 'no-cache');
    }
}
