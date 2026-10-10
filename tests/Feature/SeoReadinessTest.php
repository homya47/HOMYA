<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use App\Services\MerchantProducts;
use App\Services\Seo;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SeoReadinessTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        DB::shouldReceive('connection')->never();
        $this->root = sys_get_temp_dir().'/homya-seo-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->root.'/products');
        File::ensureDirectoryExists($this->root.'/localizations');
        $this->app->usePublicPath($this->root.'/public');
        File::ensureDirectoryExists(public_path('media/catalogue'));
        $chunk = static fn ($type, $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $png = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 500, 500, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress(str_repeat("\0".str_repeat("\xff", 1500), 500))).$chunk('IEND', '');
        $image = ['local_url' => '/media/catalogue/'.str_repeat('a', 24).'.png'];
        File::put(public_path(ltrim($image['local_url'], '/')), $png);
        $products = [];
        $dictionaries = ['en' => ['Catégorie' => 'Category', 'Description €' => 'Description €'], 'de' => ['Catégorie' => 'Kategorie', 'Description €' => 'Beschreibung €']];
        for ($i = 1; $i <= 30; $i++) {
            $product = ['slug' => 'seo-'.$i, 'reference' => 'REF-'.$i, 'name' => 'Produit '.$i, 'brand' => 'Marque',
                'category' => ['slug' => 'categorie', 'name' => 'Catégorie'], 'universe' => 'appliances', 'condition' => 'new',
                'price_cents' => 12345, 'previous_price_cents' => null, 'currency' => 'EUR', 'has_variants' => false,
                'issues' => [], 'image' => $image, 'images' => [$image], 'description' => 'Description €', 'variants' => [], 'specifications' => []];
            $products[] = $product;
            File::put($this->root.'/products/'.$product['slug'].'.json', json_encode($product));
            $dictionaries['en'][$product['name']] = 'Product '.$i;
            $dictionaries['de'][$product['name']] = 'Produkt '.$i;
        }
        File::put($this->root.'/index.json', json_encode(['meta' => ['complete' => true], 'products' => $products]));
        foreach ($dictionaries as $locale => $strings) {
            File::put($this->root.'/localizations/'.$locale.'.json', json_encode(['version' => 1, 'strings' => $strings]));
        }
        config(['catalogue.source' => 'files', 'catalogue.archive_path' => $this->root.'/index.json',
            'catalogue.products_path' => $this->root.'/products', 'catalogue.localizations_path' => $this->root.'/localizations',
            'catalogue.media_overrides_path' => $this->root.'/overrides.json', 'dashboard.enabled' => false,
            'seo.indexing_enabled' => false, 'seo.public_url' => '', 'app.debug' => false,
            'merchant.enabled' => false, 'merchant.reviews_path' => $this->root.'/reviews.json']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function production(): void
    {
        File::ensureDirectoryExists($this->root.'/sessions', 0700);
        $this->app['env'] = 'production';
        config(['session.secure' => true, 'session.encrypt' => true, 'session.http_only' => true, 'session.domain' => null, 'session.driver' => 'file', 'session.files' => $this->root.'/sessions']);
        config(['seo.indexing_enabled' => true, 'seo.public_url' => 'https://homya.eu', 'app.url' => 'https://homya.eu']);
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($dom);
    }

    private function canonical(string $html): ?string
    {
        return $this->xpath($html)->query('//link[@rel="canonical"]')->item(0)?->getAttribute('href');
    }

    public function test_local_and_staging_never_enable_indexing_or_feeds(): void
    {
        config(['seo.indexing_enabled' => true, 'seo.public_url' => 'https://homya.eu', 'merchant.enabled' => true]);
        $this->get('/fr')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /', false)->assertDontSee('Sitemap:');
        $this->get('/sitemap.xml')->assertNotFound();
        $this->get('/google/merchant/fr.xml')->assertNotFound();
    }

    public function test_origin_validation_rejects_unsafe_or_non_public_configuration(): void
    {
        foreach (['', 'http://homya.eu', 'https://localhost', 'https://127.0.0.1', 'https://preview.local',
            'https://shop.test', 'https://homya.eu/path', 'https://user:pass@homya.eu',
            'https://homya.eu?x=1', 'https://homya.eu#x', 'https://homya.eu:8080'] as $url) {
            config(['seo.public_url' => $url]);
            $this->assertNull(app(Seo::class)->origin(), $url);
        }
        config(['seo.public_url' => 'https://homya.eu/']);
        $this->assertSame('https://homya.eu', app(Seo::class)->origin());
    }

    public function test_production_requires_flag_debug_off_and_matching_https_origin(): void
    {
        $this->production();
        $this->get('/fr')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        config(['app.debug' => true]);
        $this->get('https://homya.eu/fr')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        config(['app.debug' => false, 'seo.indexing_enabled' => false]);
        $this->get('https://homya.eu/fr')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_public_home_has_reciprocal_languages_and_verification_only_on_public_host(): void
    {
        $this->production();
        config(['seo.verification' => 'verification-fixture']);
        $response = $this->get('https://homya.eu/fr')->assertOk()->assertHeader('X-Robots-Tag', 'index, follow, max-image-preview:large');
        $this->assertSame('https://homya.eu/fr', $this->canonical($response->getContent()));
        $xpath = $this->xpath($response->getContent());
        $jsonScript = $xpath->query('//script[@type="application/ld+json"]')->item(0);
        $this->assertNotNull($jsonScript);
        $nonce = $jsonScript->getAttribute('nonce');
        $this->assertNotSame('', $nonce);
        $this->assertStringContainsString("'nonce-{$nonce}'", $response->headers->get('Content-Security-Policy'));
        foreach (['fr', 'en', 'de', 'x-default'] as $locale) {
            $this->assertSame('https://homya.eu/'.($locale === 'x-default' ? 'fr' : $locale),
                $xpath->query('//head/link[@hreflang="'.$locale.'"]')->item(0)->getAttribute('href'));
        }
        $response->assertSee('verification-fixture');
        $this->get('https://preview.example.org/fr')->assertDontSee('verification-fixture')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_pagination_has_its_own_canonical_and_category_content_is_distinct(): void
    {
        $this->production();
        $response = $this->get('https://homya.eu/fr/catalogue?page=2')->assertOk();
        $this->assertSame('https://homya.eu/fr/catalogue?page=2', $this->canonical($response->getContent()));
        $response = $this->get('https://homya.eu/fr/catalogue?universe=appliances&category=categorie&page=1&utm_source=google')->assertOk();
        $this->assertSame('https://homya.eu/fr/catalogue?category=categorie', $this->canonical($response->getContent()));
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('Catégorie — HOMYA', $xpath->query('//title')->item(0)->textContent);
        $this->assertSame('Catégorie', $xpath->query('//h1')->item(0)->textContent);
        $this->get('https://homya.eu/fr/catalogue?page=99')->assertRedirect();
    }

    public function test_search_sort_facets_and_invalid_empty_results_are_not_indexed(): void
    {
        $this->production();
        foreach (['q=Produit', 'brand=Marque', 'sort=price_desc', 'min=1', 'condition=new'] as $query) {
            $response = $this->get('https://homya.eu/fr/catalogue?'.$query)->assertOk();
            $response->assertHeader('X-Robots-Tag', 'noindex, follow');
            $this->assertNull($this->canonical($response->getContent()));
        }
        $this->get('https://homya.eu/fr/promotions?discount=5')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('https://homya.eu/fr/catalogue?q=notfound')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('https://homya.eu/fr/catalogue?page=0')->assertStatus(422)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('https://homya.eu/fr/produit/missing')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('https://homya.eu/up')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_product_structured_data_cleans_context_and_has_no_fabricated_offer_or_rating(): void
    {
        $this->production();
        $response = $this->get('https://homya.eu/en/produit/seo-1?q=hello&page=2&utm_campaign=test')->assertOk();
        $this->assertSame('https://homya.eu/en/produit/seo-1', $this->canonical($response->getContent()));
        $xpath = $this->xpath($response->getContent());
        $json = json_decode($xpath->query('//script[@type="application/ld+json"]')->item(0)->textContent, true, 32, JSON_THROW_ON_ERROR);
        $product = collect($json['@graph'])->firstWhere('@type', 'Product');
        $this->assertSame('Product 1', $product['name']);
        $this->assertSame('REF-1', $product['sku']);
        $this->assertSame('https://homya.eu'.$this->product()['images'][0]['local_url'], $product['image'][0]);
        foreach (['offers', 'aggregateRating', 'review', 'gtin', 'mpn'] as $key) {
            $this->assertArrayNotHasKey($key, $product);
        }
    }

    public function test_jsonld_and_metadata_cannot_inject_script_from_product_prose(): void
    {
        $this->production();
        $product = $this->product();
        $product['description'] = str_repeat('Texte ', 70).'</script><script>alert(1)</script>';
        File::put($this->root.'/products/seo-1.json', json_encode($product));
        $response = $this->get('https://homya.eu/fr/produit/seo-1')->assertOk();
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//script')->length);
        $this->assertLessThanOrEqual(170, mb_strlen($xpath->query('//meta[@name="description"]')->item(0)->getAttribute('content')));
        $data = json_decode($xpath->query('//script')->item(0)->textContent, true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame($product['description'], collect($data['@graph'])->firstWhere('@type', 'Product')['description']);
    }

    public function test_sitemap_xml_contains_only_current_public_pages_and_has_no_invented_lastmod(): void
    {
        $this->production();
        config(['seo.sitemap_chunk_size' => 20]);
        $index = $this->get('https://homya.eu/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($index->getContent());
        $this->assertCount(9, $xml->sitemap);
        $all = [];
        foreach ($xml->sitemap as $entry) {
            $response = $this->get((string) $entry->loc)->assertOk();
            $body = $response->getContent();
            $this->assertStringNotContainsString('<lastmod>', $body);
            foreach (simplexml_load_string($body)->url as $url) {
                $all[] = (string) $url->loc;
            }
        }
        $this->assertSame(count($all), count(array_unique($all)));
        $this->assertContains('https://homya.eu/fr/produit/seo-1', $all);
        $this->assertContains('https://homya.eu/de/catalogue?category=categorie', $all);
        foreach ($all as $url) {
            $this->assertDoesNotMatchRegularExpression('/admin|panier|commande|assistance|suivi|avis|mentions-legales|q=/', $url);
        }
        $this->get('https://homya.eu/sitemaps/fr/products-3.xml')->assertNotFound();
        $this->get('https://homya.eu/sitemaps/it/pages.xml')->assertNotFound();
        $this->get('https://homya.eu/robots.txt')->assertSee('Sitemap: https://homya.eu/sitemap.xml', false)->assertDontSee('Disallow: /');
    }

    public function test_draft_information_and_private_commerce_keep_noindex(): void
    {
        $this->production();
        $this->get('https://homya.eu/fr/mentions-legales')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('https://homya.eu/fr/panier')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('https://homya.eu/fr/a-propos')->assertOk()->assertHeader('X-Robots-Tag', 'index, follow, max-image-preview:large');
    }

    private function product(): array
    {
        return app(Catalogue::class)->product('seo-1');
    }

    private function review(array $product): array
    {
        return ['approved' => true, 'fingerprints' => ['fr' => MerchantProducts::fingerprint($product)],
            'gtin' => '4006381333931', 'title_source' => 'default', 'description_source' => 'default'];
    }

    public function test_merchant_checks_identifiers_variants_changed_content_and_text_provenance(): void
    {
        $merchant = app(MerchantProducts::class);
        $product = $this->product();
        $review = $this->review($product);
        $this->assertSame([], $merchant->problems($product, $review, 'fr'));
        $malformed = $review;
        $malformed['fingerprints'] = ['fr' => ['bad']];
        $this->assertContains('merchant_review_required', $merchant->problems($product, $malformed, 'fr'));
        $this->assertSame('new', MerchantProducts::condition(['condition' => 'neuf']));
        $this->assertSame('used', MerchantProducts::condition(['condition' => 'occasion']));
        $this->assertSame('refurbished', MerchantProducts::condition(['condition' => 'reconditionne']));
        $this->assertNull(MerchantProducts::condition(['condition' => 'unknown']));
        $this->assertTrue(MerchantProducts::validGtin('4006381333931'));
        foreach (['4006381333932', '00000000', 'REF-1', 4006381333931] as $gtin) {
            $this->assertFalse(MerchantProducts::validGtin($gtin));
        }
        $product['price_cents']++;
        $this->assertContains('merchant_review_required', $merchant->problems($product, $review, 'fr'));
        $product['has_variants'] = true;
        $this->assertContains('variant_data_required', $merchant->problems($product, $review, 'fr'));
        $product['has_variants'] = false;
        unset($review['gtin']);
        $this->assertContains('manufacturer_identifiers_required', $merchant->problems($product, $review, 'fr'));
        $review['identifier_exists'] = false;
        $this->assertNotContains('manufacturer_identifiers_required', $merchant->problems($product, $review, 'fr'));
        $review['gtin'] = '4006381333931';
        $this->assertContains('identifiers_contradict_exemption', $merchant->problems($product, $review, 'fr'));
        unset($review['description_source']);
        $this->assertContains('text_provenance_required', $merchant->problems($product, $review, 'fr'));
    }

    public function test_merchant_xml_uses_exact_page_data_and_escapes_text_without_exporting_quantities(): void
    {
        config(['seo.public_url' => 'https://homya.eu']);
        $merchant = app(MerchantProducts::class);
        $product = $this->product();
        $product['description'] = 'Description <script> & €';
        $review = $this->review($product);
        $body = $merchant->xml([[$product, $review, true]], 'fr');
        $xml = simplexml_load_string($body);
        $item = $xml->channel->item->children('http://base.google.com/ns/1.0');
        $this->assertSame($product['description'], (string) $item->description);
        $this->assertSame(number_format($product['price_cents'] / 100, 2, '.', '').' EUR', (string) $item->price);
        $this->assertSame('in_stock', (string) $item->availability);
        $this->assertSame('4006381333931', (string) $item->gtin);
        $this->assertSame('https://homya.eu/fr/produit/seo-1', (string) $item->link);
        $this->assertStringNotContainsString('quantity', $body);
        $this->assertStringNotContainsString('fingerprint', $body);
        $de = simplexml_load_string($merchant->xml([[$product, $review, false]], 'de'))->channel->item->children('http://base.google.com/ns/1.0');
        $this->assertSame((string) $item->id, (string) $de->id);
        $this->assertSame('out_of_stock', (string) $de->availability);
        $review['title_source'] = 'trained_algorithmic_media';
        $ai = simplexml_load_string($merchant->xml([[$product, $review, true]], 'fr'))->channel->item->children('http://base.google.com/ns/1.0');
        $this->assertSame('trained_algorithmic_media', (string) $ai->structured_title->children('http://base.google.com/ns/1.0')->digital_source_type);
    }

    public function test_merchant_stays_closed_without_real_payment_and_audit_never_queries_inventory(): void
    {
        $this->production();
        config(['merchant.enabled' => true, 'commerce.enabled' => true, 'commerce.deferred_enabled' => true,
            'commerce.legal_ready' => true, 'commerce.inventory_enabled' => true]);
        $this->assertFalse(app(MerchantProducts::class)->ready());
        $this->get('https://homya.eu/google/merchant/fr.xml')->assertNotFound();
        config(['commerce.inventory_enabled' => false]);
        $this->artisan('homya:google-audit', ['--locale' => 'fr'])->expectsOutputToContain('30 products audited')->assertSuccessful();
        $this->artisan('homya:google-audit', ['--locale' => 'it'])->assertFailed();
    }

    public function test_offer_matches_visible_price_and_stock_only_with_real_payment_configuration(): void
    {
        $this->production();
        config(['commerce.enabled' => true, 'commerce.legal_ready' => true, 'commerce.bank.enabled' => true,
            'commerce.bank.iban' => 'FR1420041010050500013M02606', 'commerce.bank.bic' => 'PSSTFRPPXXX',
            'commerce.bank.beneficiary' => 'Fixture', 'commerce.bank.bank_name' => 'Fixture Bank']);
        $request = Request::create('https://homya.eu/fr/produit/seo-1');
        $route = (new Route('GET', '/{locale}/produit/{slug}', []))->name('product');
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        $product = $this->product();
        $seo = app(Seo::class);
        $offer = function ($stock, $entry) use ($seo, $request) {
            $page = $seo->page($request, $entry['name'], $entry['description'], ['available' => true, 'product' => $entry, 'available_stock' => $stock]);

            return collect(json_decode($page['json'], true)['@graph'])->firstWhere('@type', 'Product')['offers'] ?? null;
        };
        $this->assertSame(number_format($product['price_cents'] / 100, 2, '.', ''), $offer(1, $product)['price']);
        $this->assertSame('https://schema.org/InStock', $offer(1, $product)['availability']);
        $this->assertSame('https://schema.org/OutOfStock', $offer(0, $product)['availability']);
        $this->assertNull($offer(null, $product));
        $product['has_variants'] = true;
        $this->assertNull($offer(10, $product));
        $product['has_variants'] = false;
        $product['issues'] = ['unresolved_description'];
        $this->assertNull($offer(10, $product));
        config(['commerce.bank.enabled' => false, 'commerce.stripe.mode' => 'test',
            'commerce.stripe.secret' => 'sk_test_fixture', 'commerce.stripe.webhook_secret' => 'whsec_fixture']);
        $this->assertNull($offer(10, $this->product()));
        $this->assertFalse(app(MerchantProducts::class)->ready());
    }
}
