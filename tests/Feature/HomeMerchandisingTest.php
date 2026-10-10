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

class HomeMerchandisingTest extends TestCase
{
    private string $directory;

    private array $slugs;

    protected function setUp(): void
    {
        parent::setUp();
        DB::shouldReceive('connection')->never();
        $this->withoutVite();
        $this->directory = sys_get_temp_dir().'/homya-home-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->directory.'/localizations', 0700, true);
        File::makeDirectory($this->directory.'/public/media/catalogue', 0700, true);
        $this->app->usePublicPath($this->directory.'/public');
        config([
            'catalogue.source' => 'files',
            'catalogue.archive_path' => $this->directory.'/index.json',
            'catalogue.products_path' => $this->directory.'/products',
            'catalogue.localizations_path' => $this->directory.'/localizations',
            'catalogue.media_overrides_path' => $this->directory.'/overrides.json',
        ]);
        $chunk = static fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $image = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 500, 500, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress(str_repeat("\x00".str_repeat("\x80", 1500), 500))).$chunk('IEND', '');
        File::put($this->directory.'/public/media/catalogue/'.str_repeat('a', 24).'.png', $image);
        $this->slugs = array_merge(config('home.featured'), config('home.discovery'));
        $universes = ['firewood', 'heating', 'appliances', 'furniture', 'televisions'];
        $products = [];
        $translations = ['en' => ['Catégorie test' => 'Test category'], 'de' => ['Catégorie test' => 'Testkategorie']];
        foreach ($this->slugs as $position => $slug) {
            $name = 'Produit français '.($position + 1);
            $products[] = [
                'slug' => $slug, 'name' => $name, 'brand' => 'DAMDAM', 'reference' => 'REF-'.($position + 1),
                'category' => ['slug' => 'category-test', 'name' => 'Catégorie test'], 'universe' => $universes[$position % 5],
                'condition' => 'new', 'price_cents' => 12345 + $position, 'currency' => 'EUR', 'has_variants' => false,
                'issues' => [], 'image' => ['local_url' => '/media/catalogue/'.str_repeat('a', 24).'.png'],
                'source_url' => 'https://www.damdam.fr/private-history',
            ];
            $translations['en'][$name] = 'English product '.($position + 1);
            $translations['de'][$name] = 'Deutsches Produkt '.($position + 1);
        }
        // Source order differs deliberately from the chosen merchandising order.
        File::put($this->directory.'/index.json', json_encode(['meta' => ['complete' => true], 'products' => array_reverse($products)]));
        foreach ($translations as $locale => $strings) {
            File::put($this->directory.'/localizations/'.$locale.'.json', json_encode(['version' => 1, 'strings' => $strings]));
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }

    public function test_selected_products_are_real_localized_ordered_and_linked_without_database_writes(): void
    {
        $archiveHash = hash_file('sha256', $this->directory.'/index.json');
        foreach (['fr' => 'Produit français', 'en' => 'English product', 'de' => 'Deutsches Produkt'] as $locale => $prefix) {
            $response = $this->get('/'.$locale)->assertOk()->assertViewHas('catalogueReady', true)
                ->assertViewHas('catalogueUnavailable', false)->assertDontSee('damdam', false)
                ->assertSee($prefix.' 1')->assertSee(Catalogue::money(12345, $locale));
            $featured = $response->viewData('featuredProducts');
            $discovery = $response->viewData('discoveryProducts');
            $this->assertSame(config('home.featured'), $featured->pluck('slug')->all());
            $this->assertSame(config('home.discovery'), $discovery->pluck('slug')->all());
            $this->assertSame(12345, $featured->first()['price_cents']);
            $this->assertSame('EUR', $featured->first()['currency']);
            $this->assertArrayNotHasKey('source_url', $featured->first());
            $xpath = $this->xpath($response->getContent());
            $this->assertSame(config('home.departments'), $response->viewData('departments'));
            foreach (config('home.departments') as $department) {
                $links = $xpath->query('//*[@id="'.$department['id'].'"]//a[@href]');
                $this->assertSame(1, $links->length);
                $url = parse_url($links->item(0)->getAttribute('href'));
                $this->assertSame('/'.$locale.'/catalogue', $url['path']);
                parse_str($url['query'], $filters);
                $expected = $department['filters'];
                if ($department['key'] === 'cooling') {
                    $expected['q'] = __('home.cooling_search', [], $locale);
                }
                $this->assertSame($expected, $filters);
            }
            foreach (['featured' => 12, 'discovery' => 8] as $section => $count) {
                $links = $xpath->query('//*[@data-home-products="'.$section.'"]//article[contains(@class,"product-card")]/a');
                $this->assertSame($count, $links->length);
                foreach ($links as $position => $link) {
                    $this->assertSame('/'.$locale.'/produit/'.config('home.'.$section)[$position], parse_url($link->getAttribute('href'), PHP_URL_PATH));
                }
            }
            foreach (['firewood', 'heating', 'appliances', 'furniture', 'televisions'] as $universe) {
                $this->assertSame(500, $response->viewData('universeImages')[$universe]['width']);
            }
            if ($locale !== 'fr') {
                $response->assertDontSee('Produit français');
            }
        }
        $this->assertSame($archiveHash, hash_file('sha256', $this->directory.'/index.json'));
    }

    public function test_missing_selection_is_omitted_without_substituting_another_product(): void
    {
        $archive = json_decode(File::get($this->directory.'/index.json'), true);
        $archive['products'] = array_values(array_filter($archive['products'], fn ($product) => $product['slug'] !== $this->slugs[0]));
        File::put($this->directory.'/index.json', json_encode($archive));
        $response = $this->get('/fr')->assertOk();
        $this->assertSame(array_slice(config('home.featured'), 1), $response->viewData('featuredProducts')->pluck('slug')->all());
        $response->assertDontSee('/produit/'.$this->slugs[0], false);
    }

    public function test_incomplete_translation_does_not_fall_back_to_french_products(): void
    {
        File::put($this->directory.'/localizations/en.json', json_encode(['version' => 1, 'strings' => []]));
        $response = $this->get('/en')->assertOk()->assertViewHas('catalogueUnavailable', true)
            ->assertViewHas('catalogueReady', false)->assertDontSee('Produit français');
        $this->assertCount(0, $response->viewData('featuredProducts'));
        $this->assertCount(0, $response->viewData('discoveryProducts'));
        $this->assertSame(array_fill_keys(['firewood', 'heating', 'appliances', 'furniture', 'televisions'], null), $response->viewData('universeImages'));
    }

    public function test_empty_merchandising_selections_keep_a_localized_explanation_and_catalogue_destination(): void
    {
        // Keep the fixture's catalogue populated; only the selected references are absent.
        config(['home.featured' => ['absent-featured-product'], 'home.discovery' => ['absent-discovery-product']]);
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale)->assertOk()->assertViewHas('catalogueReady', true)
                ->assertViewHas('catalogueUnavailable', false)->assertSee(__('home.selection_empty', [], $locale));
            $this->assertCount(0, $response->viewData('featuredProducts'));
            $this->assertCount(0, $response->viewData('discoveryProducts'));
            $xpath = $this->xpath($response->getContent());
            $this->assertSame(0, $xpath->query('//article[contains(@class,"product-card")]')->length);
            foreach (['featured', 'discovery'] as $section) {
                $links = $xpath->query('//*[@data-home-products="'.$section.'"]//a[@href]');
                $this->assertGreaterThanOrEqual(1, $links->length);
                $this->assertSame('/'.$locale.'/catalogue', parse_url($links->item(0)->getAttribute('href'), PHP_URL_PATH));
            }
        }
    }

    public function test_unavailable_source_exposes_no_private_failure_or_product_data(): void
    {
        $source = Mockery::mock(CatalogueSource::class);
        $source->shouldReceive('archive')->once()->andThrow(new RuntimeException('PRIVATE_SOURCE_ACCESS_SENTINEL'));
        $source->shouldNotReceive('product', 'dictionary', 'mediaOverrides');
        $this->app->instance(CatalogueSource::class, $source);
        $response = $this->get('/fr')->assertOk()->assertViewHas('catalogueUnavailable', true)
            ->assertViewHas('catalogueReady', false)->assertDontSee('PRIVATE_SOURCE_ACCESS_SENTINEL')
            ->assertDontSee('Produit français')->assertDontSee('damdam', false);
        $this->assertCount(0, $response->viewData('featuredProducts'));
    }

    public function test_home_asks_for_one_shared_catalogue_projection(): void
    {
        $catalogue = Mockery::mock(Catalogue::class);
        $catalogue->shouldReceive('products')->once()->andReturn(collect());
        $catalogue->shouldReceive('sourceUnavailable')->once()->andReturn(false);
        $catalogue->shouldReceive('translationUnavailable')->once()->andReturn(false);
        $this->app->instance(Catalogue::class, $catalogue);
        $this->get('/fr')->assertOk()->assertViewHas('catalogueReady', false);
    }
}
