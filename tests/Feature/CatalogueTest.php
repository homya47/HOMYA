<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CatalogueTest extends TestCase
{
    private string $archive;

    protected function setUp(): void
    {
        parent::setUp();
        DB::shouldReceive('connection')->never();
        $this->archive = sys_get_temp_dir().'/homya-catalogue-test-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->archive.'/products', 0700, true);
        File::makeDirectory($this->archive.'/localizations', 0700, true);
        config(['catalogue.archive_path' => $this->archive.'/index.json', 'catalogue.products_path' => $this->archive.'/products', 'catalogue.localizations_path' => $this->archive.'/localizations', 'catalogue.media_overrides_path' => $this->archive.'/media-overrides.json']);
        $products = [];
        $translations = [
            'en' => ['Catégorie test' => 'Test category', 'Description test <script>alert(1)</script>' => 'Test description <script>alert(1)</script>', 'Largeur' => 'Width', '20 cm' => '20 cm', 'Note' => 'Note', '<img src=x onerror=alert(1)>' => '<img src=x onerror=alert(1)>'],
            'de' => ['Catégorie test' => 'Testkategorie', 'Description test <script>alert(1)</script>' => 'Testbeschreibung <script>alert(1)</script>', 'Largeur' => 'Breite', '20 cm' => '20 cm', 'Note' => 'Hinweis', '<img src=x onerror=alert(1)>' => '<img src=x onerror=alert(1)>'],
        ];
        for ($i = 1; $i <= 252; $i++) {
            $slug = 'test-produit-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $product = ['slug' => $slug, 'name' => 'Équipement test '.str_pad((string) $i, 3, '0', STR_PAD_LEFT), 'brand' => 'Marque test', 'reference' => 'REF-'.$i,
                'category' => ['slug' => 'test-categorie', 'name' => 'Catégorie test'], 'universe' => 'appliances', 'condition' => 'new',
                'price_cents' => $i === 252 ? null : ($i <= 2 ? 12345 : 12345 + $i), 'previous_price_cents' => null, 'currency' => 'EUR',
                'has_variants' => false, 'source_url' => 'https://www.damdam.fr/produit/'.$slug, 'captured_at' => '2026-10-07T00:00:00Z',
                'issues' => [], 'image' => null, 'images' => [], 'variants' => [], 'description' => 'Description test <script>alert(1)</script>',
                'specifications' => [['label' => 'Largeur', 'value' => '20 cm'], ['label' => 'Note', 'value' => '<img src=x onerror=alert(1)>']]];
            $products[] = $product;
            $translations['en'][$product['name']] = 'Test equipment '.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $translations['de'][$product['name']] = 'Testgerät '.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            File::put($this->archive.'/products/'.$slug.'.json', json_encode($product));
        }
        File::put($this->archive.'/index.json', json_encode(['meta' => ['complete' => true], 'products' => $products]));
        foreach ($translations as $locale => $strings) {
            File::put($this->archive.'/localizations/'.$locale.'.json', json_encode(['version' => 1, 'strings' => $strings]));
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->archive);
        parent::tearDown();
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }

    public function test_variant_urls_preselect_exact_option_in_every_language_without_accepting_invalid_options(): void
    {
        $path = $this->archive.'/products/test-produit-001.json';
        $product = json_decode(File::get($path), true);
        $product['has_variants'] = true;
        $product['variants'] = [['title' => '20 cm', 'sku' => 'REF-1-A'], ['title' => 'Note', 'sku' => 'REF-1-B']];
        File::put($path, json_encode($product));
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/produit/test-produit-001?variant=v2')->assertOk();
            $xpath = $this->xpath($response->getContent());
            $this->assertSame('v2', $xpath->query('//select[@name="variant"]/option[@selected]')->item(0)->getAttribute('value'));
            $this->assertStringContainsString($locale === 'de' ? 'Hinweis' : 'Note', $xpath->query('//h1')->item(0)->textContent);
            $response->assertSee('/fr/produit/test-produit-001?variant=v2', false)->assertSee('variant=v1', false);
            $response->assertDontSee(__('catalogue.variant_photo_notice', [], $locale));
        }
        foreach (['v3', 'v0', 'v02', '', ['v1']] as $invalid) {
            $this->get('/fr/produit/test-produit-001?'.http_build_query(['variant' => $invalid]))->assertNotFound();
        }
        $this->get('/fr/produit/test-produit-002?variant=v1')->assertNotFound();
        $this->assertSame($product, json_decode(File::get($path), true));
    }

    public function test_unresolved_description_uses_existing_facts_and_conflicting_specs_are_not_presented_as_verified(): void
    {
        $path = $this->archive.'/products/test-produit-001.json';
        $product = json_decode(File::get($path), true);
        $product['description'] = '$7e';
        $product['specifications'] = [['label' => 'Largeur', 'value' => '20 cm'], ['label' => 'Largeur', 'value' => 'Note']];
        $product['issues'] = ['description_requires_review', 'conflicting_specification'];
        File::put($path, json_encode($product));
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $presented = app(Catalogue::class)->product($product['slug']);
            $this->assertStringContainsString('REF-1', $presented['description']);
            $this->assertNotContains('description_requires_review', $presented['issues']);
            $this->assertContains('conflicting_specification', $presented['issues']);
            $this->assertCount(1, $presented['specifications']);
            $this->assertNotSame('20 cm', $presented['specifications'][0]['value']);
            $this->assertCount(1, $presented['specification_reviews']);
            $this->get('/'.$locale.'/produit/test-produit-001')->assertOk()->assertDontSee('$7e')->assertSee('REF-1');
        }
        $this->assertSame($product, json_decode(File::get($path), true));
    }

    private function imageFixture(int $width, int $height, string $filename): array
    {
        $this->withoutVite();
        $this->app->usePublicPath($this->archive.'/public');
        File::ensureDirectoryExists(public_path('media/catalogue'));
        $chunk = static function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        };
        $png = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress(str_repeat("\x00".str_repeat("\x80", $width * 3), $height)))
            .$chunk('IEND', '');
        $url = '/media/catalogue/'.$filename;
        File::put(public_path(ltrim($url, '/')), $png);

        return ['local_url' => $url];
    }

    private function productImages(array $images): string
    {
        $path = $this->archive.'/products/test-produit-001.json';
        $product = json_decode(File::get($path), true);
        $product['images'] = $images;
        $product['image'] = $images[0];
        File::put($path, json_encode($product));
        $index = json_decode(File::get($this->archive.'/index.json'), true);
        $index['products'][0] = $product;
        File::put($this->archive.'/index.json', json_encode($index));

        return $path;
    }

    public function test_small_photos_are_omitted_but_valid_description_is_kept_without_source_changes(): void
    {
        $smallWidth = $this->imageFixture(499, 600, str_repeat('a', 24).'.png');
        $valid = $this->imageFixture(500, 500, str_repeat('b', 24).'.png');
        $smallHeight = $this->imageFixture(600, 499, str_repeat('c', 24).'.png');
        $path = $this->productImages([$smallWidth, $valid, $smallHeight]);
        $original = File::get($path);
        $hashes = array_map(fn ($image) => hash_file('sha256', public_path(ltrim($image['local_url'], '/'))), [$smallWidth, $valid, $smallHeight]);
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/produit/test-produit-001')->assertOk()->assertSee($valid['local_url'], false)
                ->assertDontSee($smallWidth['local_url'], false)->assertDontSee($smallHeight['local_url'], false)
                ->assertDontSee(__('catalogue.content_unavailable', [], $locale))->assertSee('REF-1')->assertSee('20 cm');
            $xpath = $this->xpath($response->getContent());
            $this->assertSame(1, $xpath->query('//p[@class="product-description"]')->length);
            $this->assertSame(0, $xpath->query('//button[@data-gallery-src]')->length);
            $this->assertSame(__('catalogue.image_count', ['current' => 1, 'total' => 1], $locale), $xpath->query('//p[@data-gallery-caption]')->item(0)->textContent);
            $this->assertStringContainsString('alert(1)', $xpath->query('//meta[@name="description"]')->item(0)->getAttribute('content'));
            $this->get('/'.$locale.'/catalogue?q=REF-1')->assertOk()->assertDontSee($smallWidth['local_url'], false);
        }
        $this->assertSame($original, File::get($path));
        $this->assertSame($hashes, array_map(fn ($image) => hash_file('sha256', public_path(ltrim($image['local_url'], '/'))), [$smallWidth, $valid, $smallHeight]));
    }

    public function test_fully_hidden_gallery_has_no_controls_or_zero_image_caption_and_can_recover(): void
    {
        $image = $this->imageFixture(250, 250, str_repeat('d', 24).'.png');
        $this->productImages([$image]);
        $response = $this->get('/fr/produit/test-produit-001')->assertOk()->assertSee('123,45 €')
            ->assertSee(__('catalogue.image_missing', [], 'fr'))->assertDontSee($image['local_url'], false);
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(0, $xpath->query('//img[@data-product-image]|//button[@data-gallery-src]|//p[@data-gallery-caption]')->length);
        // A genuine replacement of adequate size restores content without editing source JSON.
        $this->imageFixture(500, 500, str_repeat('d', 24).'.png');
        $this->get('/fr/produit/test-produit-001')->assertOk()->assertSee($image['local_url'], false)
            ->assertSee('Description test')->assertDontSee(__('catalogue.content_unavailable', [], 'fr'));
    }

    public function test_wrong_image_format_is_hidden_and_valid_boundary_image_keeps_description(): void
    {
        $valid = $this->imageFixture(500, 500, str_repeat('e', 24).'.png');
        $this->productImages([$valid]);
        $this->get('/en/produit/test-produit-001')->assertOk()->assertSee('Test description')->assertSee($valid['local_url'], false);
        $disguised = $this->imageFixture(500, 500, str_repeat('f', 24).'.jpg');
        $this->productImages([$disguised]);
        $this->get('/en/produit/test-produit-001')->assertOk()->assertDontSee($disguised['local_url'], false)
            ->assertSee('Test description')->assertDontSee(__('catalogue.content_unavailable', [], 'en'));
    }

    public function test_index_card_uses_a_valid_secondary_photo_when_the_primary_is_too_small(): void
    {
        $small = $this->imageFixture(450, 450, str_repeat('1', 24).'.png');
        $valid = $this->imageFixture(600, 700, str_repeat('2', 24).'.png');
        $path = $this->productImages([$small, $valid]);
        $original = File::get($path);
        $index = json_decode(File::get($this->archive.'/index.json'), true);
        unset($index['products'][0]['images'], $index['products'][0]['description']);
        File::put($this->archive.'/index.json', json_encode($index));
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->get('/'.$locale.'/catalogue?q=REF-1&min=123.45&max=123.45')->assertOk()
                ->assertSee($valid['local_url'], false)->assertDontSee($small['local_url'], false);
        }
        $this->assertSame($original, File::get($path));
    }

    public function test_verified_replacement_is_shared_by_cards_and_galleries_without_exposing_provenance(): void
    {
        $small = $this->imageFixture(450, 450, str_repeat('3', 24).'.png');
        $replacement = $this->imageFixture(1600, 1062, str_repeat('4', 24).'.png');
        $path = $this->productImages([$small]);
        $original = File::get($path);
        $digest = hash_file('sha256', public_path(ltrim($small['local_url'], '/')));
        File::put($this->archive.'/media-overrides.json', json_encode(['version' => 1, 'products' => [
            'test-produit-001' => ['reference' => 'REF-1', 'visual_review' => 'verified', 'image' => $replacement,
                'source_page' => 'https://www.damdam.fr/private-provenance', 'source_image' => 'https://manufacturer.example/photo.jpg'],
        ]]));
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->get('/'.$locale.'/produit/test-produit-001')->assertOk()->assertSee($replacement['local_url'], false)
                ->assertDontSee($small['local_url'], false)->assertDontSee('manufacturer.example', false)->assertDontSee('damdam', false)
                ->assertDontSee(__('catalogue.content_unavailable', [], $locale))->assertSee('REF-1');
            $this->get('/'.$locale.'/catalogue?q=REF-1&min=123.45&max=123.45')->assertOk()->assertSee($replacement['local_url'], false);
        }
        $this->assertSame($original, File::get($path));
        $this->assertSame($digest, hash_file('sha256', public_path(ltrim($small['local_url'], '/'))));
    }

    public function test_bad_or_unverified_replacement_falls_back_to_a_genuine_valid_gallery_photo(): void
    {
        $small = $this->imageFixture(450, 450, str_repeat('5', 24).'.png');
        $valid = $this->imageFixture(500, 500, str_repeat('6', 24).'.png');
        $unrelated = $this->imageFixture(600, 600, str_repeat('7', 24).'.png');
        $this->productImages([$small, $valid]);
        $base = ['reference' => 'REF-1', 'visual_review' => 'verified', 'image' => $unrelated];
        foreach ([array_replace($base, ['reference' => 'WRONG']), array_replace($base, ['visual_review' => 'pending']),
            array_replace($base, ['image' => $small]), array_replace($base, ['image' => ['local_url' => '/media/catalogue/'.str_repeat('8', 24).'.png']]),
            array_replace($base, ['image' => ['local_url' => 'https://manufacturer.example/image.png']])] as $entry) {
            File::put($this->archive.'/media-overrides.json', json_encode(['version' => 1, 'products' => ['test-produit-001' => $entry]]));
            $this->get('/fr/produit/test-produit-001')->assertOk()->assertSee($valid['local_url'], false)->assertDontSee($unrelated['local_url'], false);
            $this->get('/fr/catalogue?q=REF-1&min=123.45&max=123.45')->assertOk()->assertSee($valid['local_url'], false)->assertDontSee($unrelated['local_url'], false);
        }
        File::put($this->archive.'/media-overrides.json', '{malformed');
        $this->get('/fr/produit/test-produit-001')->assertOk()->assertSee($valid['local_url'], false);
    }

    public function test_catalogue_is_browsable_in_all_languages_without_database_or_live_payment(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/catalogue')->assertOk()->assertSee('lang="'.$locale.'"', false);
            $xpath = $this->xpath($response->getContent());
            $this->assertSame(24, $xpath->query('//article[@class="product-card"]')->length);
            $this->assertSame(1, $xpath->query('//h1')->length);
            $this->assertSame(1, $xpath->query('//nav[@class="pagination"]//*[@aria-current="page"]')->length);
            $this->assertSame(0, $xpath->query('//input[@name="quantity" and @type!="hidden"]')->length);
            $this->assertSame(0, $xpath->query('//input[@id="catalogue-search"][@disabled]')->length);
            $response->assertDontSee(__('catalogue.preview_notice', [], $locale));
            $response->assertDontSee('damdam', false)->assertDontSee('source descriptions', false);
        }
    }

    public function test_pagination_keeps_filters_language_and_page_and_supports_first_last_and_jump(): void
    {
        $query = ['q' => 'equipement', 'brand' => 'Marque test', 'min' => '123,45', 'sort' => 'price_asc', 'page' => 6];
        $response = $this->get('/fr/catalogue?'.http_build_query($query))->assertOk();
        $xpath = $this->xpath($response->getContent());
        $numbers = [];
        foreach ($xpath->query('//ul[@class="pagination-links"]//a[@href]') as $link) {
            $url = parse_url($link->getAttribute('href'));
            parse_str($url['query'], $actual);
            foreach (array_diff_key($query, ['page' => 1]) as $key => $value) {
                $this->assertSame($value, $actual[$key]);
            }
            $numbers[] = (int) $actual['page'];
            $this->assertSame('results-heading', $url['fragment']);
        }
        $this->assertContains(1, $numbers);
        $this->assertContains(11, $numbers);
        foreach ($xpath->query('//a[@data-language-link]') as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $actual);
            $this->assertSame('6', $actual['page']);
            $this->assertSame('equipement', $actual['q']);
        }
        $this->assertSame('11', $xpath->query('//input[@id="jump-page"]')->item(0)->getAttribute('max'));
        $this->assertSame('equipement', $xpath->query('//form[@class="page-jump"]/input[@name="q"]')->item(0)->getAttribute('value'));
        $last = $this->get('/fr/catalogue?page=11')->assertOk();
        $this->assertSame(12, $this->xpath($last->getContent())->query('//article[@class="product-card"]')->length);
        $this->assertSame(0, $this->xpath($last->getContent())->query('//a[@rel="next"]')->length);
        $this->get('/fr/catalogue?page=99&q=equipement')->assertRedirect(route('catalogue', ['locale' => 'fr', 'q' => 'equipement', 'page' => 11]));
    }

    public function test_search_price_filter_sort_and_empty_results_are_consistent(): void
    {
        $response = $this->get('/fr/catalogue?q=REF-1&min=123.45&max=123,45')->assertOk();
        $this->assertSame(1, $this->xpath($response->getContent())->query('//article[@class="product-card"]')->length);
        $response->assertSee('123,45 €')->assertSee('test-produit-001');
        $response = $this->get('/fr/catalogue?sort=price_desc')->assertOk();
        $first = $this->xpath($response->getContent())->query('//article[@class="product-card"]/a')->item(0)->getAttribute('href');
        $this->assertStringEndsWith('test-produit-251', parse_url($first, PHP_URL_PATH));
        parse_str(parse_url($first, PHP_URL_QUERY), $context);
        $this->assertSame(['sort' => 'price_desc'], $context);
        $empty = $this->get('/fr/catalogue?q=aucune-correspondance')->assertOk()->assertSee(__('catalogue.empty', [], 'fr'));
        $this->assertSame(0, $this->xpath($empty->getContent())->query('//nav[@class="pagination"]')->length);
    }

    public function test_invalid_input_is_handled_without_error_or_unsafe_echo(): void
    {
        foreach (['q[]=test', 'brand[]=test', 'page=0', 'page=-1', 'page=abc', 'page[]=1', 'min=-1', 'min=1.123', 'min=200&max=100', 'category=absent', 'sort=absent'] as $query) {
            $this->get('/fr/catalogue?'.$query)->assertStatus(422)->assertSee(__('catalogue.invalid_filters', [], 'fr'));
        }
        $this->get('/fr/catalogue?q='.urlencode('<script>alert(1)</script>'))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_product_specifications_are_escaped_and_missing_data_does_not_get_invented(): void
    {
        $this->get('/fr/produit/test-produit-001')->assertOk()->assertSee('20 cm')->assertSee('123,45 €')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertSee(__('catalogue.image_missing', [], 'fr'))->assertDontSee('damdam.fr', false);
        $this->get('/fr/produit/test-produit-252')->assertOk()->assertSee(__('catalogue.price_missing', [], 'fr'));
        $this->get('/fr/produit/inconnu')->assertNotFound()->assertSee(__('catalogue.not_found', [], 'fr'));
        $this->get('/es/catalogue')->assertNotFound();
        File::put($this->archive.'/products/test-produit-001.json', 'invalid json');
        $this->get('/fr/produit/test-produit-001')->assertNotFound();
    }

    public function test_product_selection_preserves_invalid_quantity_and_exposes_real_basket_count(): void
    {
        $this->withoutVite();
        $url = '/fr/produit/test-produit-001';
        $this->from($url)->post('/fr/panier', ['slug' => 'test-produit-001', 'quantity' => 100])->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('value="100"', false)->assertSee(__('commerce.correct_errors'));
        $this->post('/fr/panier', ['slug' => 'test-produit-001', 'quantity' => 2])->assertRedirect('/fr/panier');
        $response = $this->get('/fr/catalogue?q=REF-1&min=123.45&max=123.45')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('2', $xpath->query('//*[contains(@class,"cart-count")]')->item(0)->textContent);
        $this->assertSame(1, $xpath->query('//article//form[@method="post"]')->length);
        $this->assertSame('1', $xpath->query('//article//input[@name="quantity"]')->item(0)->getAttribute('value'));
        $response->assertDontSee('30 jours')->assertDontSee('4,5/5');
        $this->from('/fr/catalogue')->post('/fr/panier', ['slug' => 'test-produit-001', 'quantity' => 100])->assertRedirect('/fr/catalogue');
        $this->get('/fr/catalogue')->assertSee(__('commerce.correct_errors'))
            ->assertSee(__('validation.max.numeric', ['max' => config('commerce.max_quantity')]))->assertDontSee('validation.max.numeric');
    }

    public function test_free_return_policy_is_consistent_in_each_language_and_keeps_dispatch_deadline_distinct(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $product = $this->get('/'.$locale.'/produit/test-produit-001')->assertOk()->assertSee(__('commerce.free_returns', [], $locale));
            $this->assertSame(1, $this->xpath($product->getContent())->query('//details[@id="product-purchase-information" and @open]')->length);
            $cards = $this->get('/'.$locale.'/catalogue')->assertOk();
            $xpath = $this->xpath($cards->getContent());
            $this->assertSame($xpath->query('//article[contains(@class,"product-card")]')->length,
                $xpath->query('//article[contains(@class,"product-card")]//details[contains(@class,"purchase-information") and not(@open)]')->length);
            $cart = $this->withSession(['commerce.cart' => ['test-produit-001' => 1, 'test-produit-002' => 2]])
                ->get('/'.$locale.'/panier')->assertOk()->assertSee(__('commerce.clear_price_help', [], $locale));
            $this->assertSame(2, $this->xpath($cart->getContent())->query('//article[contains(@class,"cart-line")]//details[contains(@class,"purchase-information")]')->length);
            $cart->assertDontSee('TVA 20')->assertDontSee('grossiste')->assertDontSee('plus bas du marché')->assertDontSee('sous scellé');
            $this->get('/'.$locale.'/retractation')->assertOk()->assertSee('14');
            $terms = __('legal.entries.retractation.sections', [], $locale);
            $this->assertStringContainsString('14', $terms[2]['paragraphs'][0]);
            $this->assertStringContainsString('HOMYA', $terms[2]['paragraphs'][4]);
        }
    }

    public function test_unavailable_or_corrupt_archive_has_a_clear_service_state(): void
    {
        File::put($this->archive.'/index.json', 'invalid json');
        $this->get('/fr/catalogue')->assertStatus(503)->assertSee(__('catalogue.unavailable', [], 'fr'));
        File::put($this->archive.'/index.json', json_encode(['meta' => 'invalid', 'products' => 'invalid']));
        $this->get('/fr/catalogue')->assertStatus(503);
    }

    public function test_language_changes_product_content_metadata_filters_and_search_without_changing_values(): void
    {
        foreach (['en' => ['Test equipment 001', 'Test category', 'Test description', 'Width', '123.45 €', 'equipment'], 'de' => ['Testgerät 001', 'Testkategorie', 'Testbeschreibung', 'Breite', '123,45 €', 'Testgerät']] as $locale => $expected) {
            $response = $this->get('/'.$locale.'/produit/test-produit-001')->assertOk();
            foreach (array_slice($expected, 0, 5) as $text) {
                $response->assertSee($text);
            }
            $response->assertSee('REF-1')->assertSee('20 cm')->assertSee('Marque test')->assertDontSee('Équipement test')->assertDontSee('Largeur');
            $xpath = $this->xpath($response->getContent());
            $this->assertSame($expected[0].' — HOMYA', $xpath->query('//title')->item(0)->textContent);
            $this->assertStringStartsWith($expected[2], $xpath->query('//meta[@name="description"]')->item(0)->getAttribute('content'));
            $this->get('/'.$locale.'/catalogue?q='.urlencode($expected[5]))->assertOk()->assertSee($expected[1])->assertSee($expected[0])->assertDontSee('Équipement test');
            $this->get('/'.$locale.'/catalogue?q=equipement')->assertOk()->assertSee($expected[0])->assertDontSee('Équipement test');
        }
    }

    public function test_missing_or_stale_translation_never_silently_displays_french(): void
    {
        File::put($this->archive.'/localizations/en.json', 'invalid json');
        $this->get('/en/catalogue')->assertStatus(503)->assertDontSee('Équipement test');
        $this->get('/en/produit/test-produit-001')->assertStatus(503)->assertSee(__('catalogue.translation_unavailable', [], 'en'))->assertDontSee('Description test');
        $this->get('/fr/produit/test-produit-001')->assertOk()->assertSee('Équipement test');
        $de = json_decode(File::get($this->archive.'/localizations/de.json'), true);
        unset($de['strings']['Largeur']);
        File::put($this->archive.'/localizations/de.json', json_encode($de));
        $this->get('/de/produit/test-produit-001')->assertStatus(503)->assertDontSee('Largeur');
    }

    public function test_public_identity_has_no_source_links_and_localized_variants_keep_sku(): void
    {
        $path = $this->archive.'/products/test-produit-001.json';
        $product = json_decode(File::get($path), true);
        $product['brand'] = 'DAMDAM';
        $product['name'] = 'DAMDAM test';
        $product['previous_price_cents'] = 20000;
        $product['has_variants'] = true;
        $product['variants'] = [['title' => 'Acier blanc', 'sku' => 'REF-1-ACIER-BLANC']];
        $product['images'] = [['source_url' => 'https://damdam.b-cdn.net/photo.jpg', 'local_url' => 'https://damdam.b-cdn.net/photo.jpg']];
        File::put($path, json_encode($product));
        foreach (['en' => ['HOMYA test' => 'HOMYA test', 'Acier blanc' => 'White steel'], 'de' => ['HOMYA test' => 'HOMYA Test', 'Acier blanc' => 'Weißer Stahl']] as $locale => $extra) {
            $file = $this->archive.'/localizations/'.$locale.'.json';
            $data = json_decode(File::get($file), true);
            $data['strings'] += $extra;
            File::put($file, json_encode($data));
            $this->get('/'.$locale.'/produit/test-produit-001')->assertOk()->assertSee($extra['Acier blanc'])->assertSee('REF-1-ACIER-BLANC')
                ->assertDontSee('damdam', false)->assertDontSee('<del>', false)->assertDontSee('b-cdn.net', false);
        }
        $this->assertSame('DAMDAM', json_decode(File::get($path), true)['brand']);
    }

    public function test_unresolved_description_is_not_rendered_or_replaced_with_invented_prose(): void
    {
        $path = $this->archive.'/products/test-produit-001.json';
        $product = json_decode(File::get($path), true);
        $product['description'] = '$7e';
        File::put($path, json_encode($product));
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->get('/'.$locale.'/produit/test-produit-001')->assertOk()->assertDontSee('$7e')->assertSee('REF-1');
        }
        $this->assertSame('$7e', json_decode(File::get($path), true)['description']);
    }
}
