<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CataloguePromotionPricingTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
        $this->fixture = sys_get_temp_dir().'/homya-final-prices-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->fixture.'/products', 0700, true);
        File::makeDirectory($this->fixture.'/localizations', 0700, true);
        config(['catalogue.source' => 'files', 'catalogue.archive_path' => $this->fixture.'/index.json',
            'catalogue.products_path' => $this->fixture.'/products', 'catalogue.localizations_path' => $this->fixture.'/localizations',
            'catalogue.media_overrides_path' => $this->fixture.'/absent-overrides.json',
            'home.featured' => ['price-five', 'price-fifteen', 'price-twentyfive'], 'home.discovery' => []]);
        $definitions = ['price-five' => [149999, 'EUR'], 'price-fifteen' => [150000, 'EUR'], 'price-twentyfive' => [200001, 'EUR'],
            'price-below' => [99999, 'EUR'], 'price-missing' => [null, 'EUR'], 'price-other-currency' => [150000, 'USD']];
        $products = [];
        $strings = ['en' => ['Comparaison' => 'Comparison', 'Autres références' => 'Other products'],
            'de' => ['Comparaison' => 'Vergleich', 'Autres références' => 'Andere Produkte']];
        foreach ($definitions as $slug => [$price, $currency]) {
            $eligible = in_array($slug, ['price-five', 'price-fifteen', 'price-twentyfive'], true);
            $product = ['slug' => $slug, 'name' => 'Modèle français '.$slug, 'brand' => 'Marque test', 'reference' => 'REF-'.$slug,
                'category' => ['slug' => $eligible ? 'comparison' : 'other-products', 'name' => $eligible ? 'Comparaison' : 'Autres références'],
                'universe' => 'appliances', 'condition' => 'new', 'price_cents' => $price, 'currency' => $currency,
                'has_variants' => false, 'issues' => [], 'image' => null, 'images' => [], 'description' => '', 'specifications' => [], 'variants' => [],
                'source_url' => 'https://www.damdam.fr/private-history'];
            $products[] = $product;
            $strings['en'][$product['name']] = 'English model '.$slug;
            $strings['de'][$product['name']] = 'Deutsches Modell '.$slug;
            File::put($this->fixture.'/products/'.$slug.'.json', json_encode($product));
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

    private function hashes(): array
    {
        $hashes = [];
        foreach (File::allFiles($this->fixture) as $file) {
            $hashes[$file->getRelativePathname()] = hash_file('sha256', $file->getPathname());
        }
        ksort($hashes);

        return $hashes;
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }

    public function test_repeated_projection_detail_and_promotions_never_accumulate_discounts_or_mutate_archives(): void
    {
        $before = $this->hashes();
        $expected = ['price-five' => [149999, 142499, 5], 'price-fifteen' => [150000, 127500, 15], 'price-twentyfive' => [200001, 150001, 25]];
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $catalogue = app(Catalogue::class);
            for ($repeat = 0; $repeat < 3; $repeat++) {
                $products = $catalogue->products()->keyBy('slug');
                foreach ($expected as $slug => [$base, $final, $percent]) {
                    $this->assertSame($final, $products[$slug]['price_cents']);
                    $this->assertSame($base, $products[$slug]['promotion']['base_price_cents']);
                    $this->assertSame($percent, $products[$slug]['promotion']['percent']);
                    $this->assertSame($base - $final, $products[$slug]['promotion']['saving_cents']);
                    $detail = $catalogue->product($slug);
                    $this->assertSame($products[$slug]['price_cents'], $detail['price_cents']);
                    $this->assertSame($products[$slug]['promotion'], $detail['promotion']);
                }
                foreach (['price-below' => 99999, 'price-missing' => null, 'price-other-currency' => 150000] as $slug => $unchanged) {
                    $this->assertSame($unchanged, $products[$slug]['price_cents']);
                    $this->assertNull($products[$slug]['promotion']);
                }
                $this->assertSame(149999, collect($catalogue->archive()['products'])->firstWhere('slug', 'price-five')['price_cents']);
            }
            $promotions = $this->get('/'.$locale.'/promotions?discount=15')->assertOk()->viewData('products');
            $this->assertSame(1, $promotions->total());
            $this->assertSame('price-fifteen', $promotions->first()['slug']);
            $this->assertSame(127500, $promotions->first()['price_cents']);
            $this->assertSame(15, $promotions->first()['promotion']['percent']);
        }
        $this->assertSame($before, $this->hashes());
    }

    public function test_catalogue_price_filters_and_sort_use_final_prices_rather_than_source_prices(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/catalogue?category=comparison&sort=price_asc')->assertOk();
            $this->assertSame(['price-fifteen', 'price-five', 'price-twentyfive'], $response->viewData('products')->pluck('slug')->all());
            $descending = $this->get('/'.$locale.'/catalogue?category=comparison&sort=price_desc')->assertOk();
            $this->assertSame(['price-twentyfive', 'price-five', 'price-fifteen'], $descending->viewData('products')->pluck('slug')->all());
            $atFinal = $this->get('/'.$locale.'/catalogue?q=REF-price-fifteen&min=1275.00&max=1275,00')->assertOk();
            $this->assertSame(['price-fifteen'], $atFinal->viewData('products')->pluck('slug')->all());
            $atSource = $this->get('/'.$locale.'/catalogue?q=REF-price-five&min=1499.99&max=1499,99')->assertOk();
            $this->assertSame(0, $atSource->viewData('products')->total());
        }
    }

    public function test_cards_product_page_and_home_share_final_prices_original_strikethrough_and_discount_badges(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $original = Catalogue::money(149999, $locale);
            $final = Catalogue::money(142499, $locale);
            foreach (['/'.$locale.'/catalogue?q=REF-price-five', '/'.$locale.'/promotions?q=REF-price-five', '/'.$locale, '/'.$locale.'/produit/price-five'] as $path) {
                $response = $this->get($path)->assertOk()->assertSee($original)->assertSee($final)->assertDontSee('damdam', false);
                $this->assertDoesNotMatchRegularExpression('/simulat|simuliert|vorschau/i', $response->getContent());
                $xpath = $this->xpath($response->getContent());
                $container = str_contains($path, '/produit/') ? '//section[@class="product-summary"]' : '//article[.//a[contains(@href,"/produit/price-five")]]';
                $visual = str_contains($path, '/produit/') ? '//section[@class="gallery"]//div[@class="product-card-visual"]' : $container.'//div[@class="product-card-visual"]';
                $this->assertSame(1, $xpath->query($visual.'//*[contains(@class,"promotion-badge")]')->length);
                $this->assertSame(1, $xpath->query($visual.'//*[@data-product-media]')->length);
                $this->assertSame(1, $xpath->query($container.'//*[contains(@class,"promotion-original-price")]')->length);
                $originalNode = $xpath->query($container.'//s')->item(0);
                $this->assertNotNull($originalNode);
                $this->assertStringContainsString($original, $originalNode->textContent);
                $price = $xpath->query($container.'//*[contains(@class,"promotion-price") and not(contains(@class,"promotion-original-price"))]')->item(0);
                $this->assertNotNull($price);
                $this->assertStringContainsString($final, $price->textContent);
            }
        }
    }
}
