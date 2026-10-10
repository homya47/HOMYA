<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use App\Services\CatalogueSource;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PromotionsTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
        $this->fixture = sys_get_temp_dir().'/homya-promotions-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->fixture.'/products', 0700, true);
        File::makeDirectory($this->fixture.'/localizations', 0700, true);
        config(['catalogue.source' => 'files', 'catalogue.archive_path' => $this->fixture.'/index.json',
            'catalogue.products_path' => $this->fixture.'/products', 'catalogue.localizations_path' => $this->fixture.'/localizations',
            'catalogue.media_overrides_path' => $this->fixture.'/no-overrides.json']);
        $products = [];
        $strings = ['en' => ['Catégorie témoin' => 'Fixture category'], 'de' => ['Catégorie témoin' => 'Testkategorie']];
        for ($i = 1; $i <= 64; $i++) {
            $number = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $price = $i <= 30 ? 100000 + $i : ($i <= 55 ? 150000 + $i - 31 : 200001 + $i - 56);
            if ($i === 61) {
                $price = null;
            } elseif ($i === 62) {
                $price = 99999;
            }
            $product = ['slug' => 'promotion-fixture-'.$number, 'name' => 'Équipement témoin '.$number,
                'brand' => 'Marque témoin', 'reference' => 'PROMO-'.$number,
                'category' => ['slug' => 'fixture-category', 'name' => 'Catégorie témoin'],
                'universe' => 'appliances', 'condition' => 'new', 'currency' => $i >= 63 ? ($i === 63 ? 'USD' : null) : 'EUR',
                'price_cents' => $price, 'has_variants' => false, 'issues' => [], 'image' => null, 'images' => [],
                'description' => '', 'specifications' => [], 'variants' => [],
                'source_url' => 'https://www.damdam.fr/private-history'];
            $products[] = $product;
            $strings['en'][$product['name']] = 'Fixture appliance '.$number;
            $strings['de'][$product['name']] = 'Testgerät '.$number;
            File::put($this->fixture.'/products/'.$product['slug'].'.json', json_encode($product));
        }
        File::put($this->fixture.'/index.json', json_encode(['meta' => ['complete' => true], 'products' => $products]));
        foreach ($strings as $locale => $translations) {
            File::put($this->fixture.'/localizations/'.$locale.'.json', json_encode(['version' => 1, 'strings' => $translations]));
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixture);
        parent::tearDown();
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }

    public function test_final_prices_and_product_translations_render_consistently_without_changing_archived_prices(): void
    {
        $hash = hash_file('sha256', $this->fixture.'/index.json');
        foreach (['fr' => 'Équipement témoin 001', 'en' => 'Fixture appliance 001', 'de' => 'Testgerät 001'] as $locale => $name) {
            $response = $this->get('/'.$locale.'/promotions?q=PROMO-001')->assertOk()->assertSee($name)
                ->assertSee(Catalogue::money(100001, $locale))->assertSee(Catalogue::money(95001, $locale))
                ->assertDontSee('damdam', false);
            $this->assertSame(1, $response->viewData('products')->total());
            $product = $response->viewData('products')->first();
            $this->assertSame(95001, $product['price_cents']);
            $this->assertSame(['percent' => 5, 'price_cents' => 95001, 'saving_cents' => 5000, 'base_price_cents' => 100001], $product['promotion']);
            $xpath = $this->xpath($response->getContent());
            $this->assertSame(1, $xpath->query('//article[@class="product-card"]')->length);
            $this->assertSame(1, $xpath->query('//article//s')->length);
            $this->assertStringContainsString(Catalogue::money(100001, $locale), $xpath->query('//article//s')->item(0)->textContent);
            $this->assertSame(1, $xpath->query('//article//*[contains(@class,"promotion-badge")]')->length);
            $this->assertSame(1, $xpath->query('//article//*[contains(@class,"promotion-price") and not(contains(@class,"promotion-original-price"))]')->length);
            $this->assertSame(0, $xpath->query('//*[contains(@class,"promotion-rules") or contains(@class,"promotion-bands")]')->length);
            $this->assertDoesNotMatchRegularExpression('/simulat|simuliert|vorschau/i', $response->getContent());
            $this->assertSame(0, $xpath->query('//input[@name="quantity" and @type!="hidden"]')->length);
            if ($locale !== 'fr') {
                $response->assertDontSee('Équipement témoin');
            }
            $this->get('/'.$locale.'/produit/promotion-fixture-001')->assertOk()
                ->assertSee(Catalogue::money(100001, $locale))->assertSee(Catalogue::money(95001, $locale))
                ->assertSee('promotion-price', false)->assertSee('promotion-original-price', false);
        }
        $this->assertSame($hash, hash_file('sha256', $this->fixture.'/index.json'));
    }

    public function test_only_euro_products_with_an_eligible_known_price_are_included(): void
    {
        $response = $this->get('/fr/promotions')->assertOk();
        $this->assertSame(60, $response->viewData('products')->total());
        $this->assertSame(24, $response->viewData('products')->count());
        foreach ([61, 62, 63, 64] as $number) {
            $this->get('/fr/promotions?q=PROMO-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT))->assertOk()
                ->assertSee(__('promotions.empty', [], 'fr'))->assertViewHas('products', fn ($products) => $products->isEmpty());
        }
    }

    public function test_pagination_search_discount_and_language_links_remain_on_the_promotions_route(): void
    {
        $query = ['q' => 'PROMO', 'discount' => '5', 'page' => '2'];
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/promotions?'.http_build_query($query))->assertOk();
            $this->assertSame(30, $response->viewData('products')->total());
            $this->assertSame(6, $response->viewData('products')->count());
            $xpath = $this->xpath($response->getContent());
            $this->assertSame(6, $xpath->query('//article[@class="product-card"]')->length);
            foreach ($xpath->query('//nav[@class="pagination"]//a[@href]') as $link) {
                $url = parse_url($link->getAttribute('href'));
                $this->assertSame('/'.$locale.'/promotions', $url['path']);
                $this->assertSame('results-heading', $url['fragment']);
                parse_str($url['query'], $filters);
                $this->assertSame('PROMO', $filters['q']);
                $this->assertSame('5', $filters['discount']);
            }
            $jump = $xpath->query('//form[@class="page-jump"]')->item(0);
            $this->assertSame('/'.$locale.'/promotions', parse_url($jump->getAttribute('action'), PHP_URL_PATH));
            $this->assertSame('results-heading', parse_url($jump->getAttribute('action'), PHP_URL_FRAGMENT));
            $this->assertSame('PROMO', $xpath->query('//form[@class="page-jump"]/input[@name="q"]')->item(0)->getAttribute('value'));
            $this->assertSame('5', $xpath->query('//form[@class="page-jump"]/input[@name="discount"]')->item(0)->getAttribute('value'));
            foreach ($xpath->query('//a[@data-language-link]') as $link) {
                $this->assertStringEndsWith('/promotions', parse_url($link->getAttribute('href'), PHP_URL_PATH));
                parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $filters);
                $this->assertSame($query, $filters);
            }
            $this->assertSame(24, $this->get('/'.$locale.'/promotions?discount=5')->assertOk()->viewData('products')->count());
        }
    }

    public function test_each_discount_band_renders_only_its_eligible_products_and_the_expected_prices(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach ([5 => [30, 100030, 95029], 15 => [25, 150023, 127520], 25 => [5, 200005, 150004]] as $percent => [$total, $base, $final]) {
                $response = $this->get('/'.$locale.'/promotions?discount='.$percent)->assertOk()
                    ->assertSee(Catalogue::money($base, $locale))->assertSee(Catalogue::money($final, $locale));
                $this->assertSame($total, $response->viewData('products')->total());
                $this->assertSame($final, $response->viewData('products')->first()['price_cents']);
                $this->assertSame($base, $response->viewData('products')->first()['promotion']['base_price_cents']);
                $this->assertSame($final, $response->viewData('products')->first()['promotion']['price_cents']);
                $this->assertSame([$percent], $response->viewData('products')->pluck('promotion.percent')->unique()->values()->all());
            }
        }
    }

    public function test_invalid_search_rate_and_page_receive_localized_422_errors(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach ([['q' => ['unsafe']], ['q' => str_repeat('x', 121)], ['discount' => '10'], ['discount' => ['5']],
                ['page' => '0'], ['page' => 'abc'], ['page' => ['1']], ['page' => '100001']] as $query) {
                $response = $this->get('/'.$locale.'/promotions?'.http_build_query($query))->assertStatus(422)
                    ->assertSee(__('promotions.invalid_filters', [], $locale));
                $this->assertFalse($response->headers->has('Location'));
                $this->assertSame(0, $this->xpath($response->getContent())->query('//article[@class="product-card"]')->length);
            }
        }
    }

    public function test_excessive_page_redirects_to_the_last_page_and_keeps_search_and_rate(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->get('/'.$locale.'/promotions?q=PROMO&discount=5&page=99')->assertRedirect(route('promotions', [
                'locale' => $locale, 'q' => 'PROMO', 'discount' => '5', 'page' => 2,
            ]));
        }
    }

    public function test_zero_matching_products_is_a_successful_explicit_empty_state(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/promotions?q=no-fixture-match')->assertOk()->assertSee(__('promotions.empty', [], $locale));
            $this->assertSame(0, $response->viewData('products')->total());
            $this->assertSame(0, $this->xpath($response->getContent())->query('//nav[@class="pagination"]|//article[@class="product-card"]')->length);
        }
    }

    public function test_source_failure_is_503_without_private_details_or_catalogue_fallback(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $source = Mockery::mock(CatalogueSource::class);
            $source->shouldReceive('archive')->once()->andThrow(new RuntimeException('PRIVATE_PROMO_SENTINEL mysql://private SQL'));
            $this->app->instance(CatalogueSource::class, $source);
            $this->get('/'.$locale.'/promotions')->assertStatus(503)->assertSee(__('catalogue.source_unavailable', [], $locale))
                ->assertDontSee('PRIVATE_PROMO_SENTINEL')->assertDontSee('mysql://private')->assertDontSee('Équipement témoin');
        }
    }

    public function test_missing_translation_is_503_without_silently_using_french_product_content(): void
    {
        foreach (['en', 'de'] as $locale) {
            File::put($this->fixture.'/localizations/'.$locale.'.json', json_encode(['version' => 1, 'strings' => []]));
            $this->get('/'.$locale.'/promotions')->assertStatus(503)
                ->assertSee(__('catalogue.translation_catalogue_unavailable', [], $locale))->assertDontSee('Équipement témoin');
        }
    }

    public function test_language_flags_are_decorative_and_each_link_keeps_a_text_label(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $xpath = $this->xpath($this->get('/'.$locale.'/promotions')->assertOk()->getContent());
            $links = $xpath->query('//nav[contains(@class,"languages")]/a');
            $this->assertSame(3, $links->length);
            $this->assertSame(3, $xpath->query('//nav[contains(@class,"languages")]//svg[@data-flag]')->length);
            foreach ($links as $link) {
                $language = $link->getAttribute('lang');
                $this->assertSame(__('home.language_names.'.$language, [], $locale), $link->getAttribute('aria-label'));
                $this->assertStringContainsString(strtoupper($language), $link->textContent);
                $flag = $xpath->query('./svg[@data-flag="'.$language.'"]', $link)->item(0);
                $this->assertNotNull($flag);
                $this->assertSame('true', $flag->getAttribute('aria-hidden'));
                $this->assertSame('false', $flag->getAttribute('focusable'));
            }
        }
    }
}
