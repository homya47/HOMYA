<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CatalogueUxRegressionTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        DB::shouldReceive('connection')->never();
        $this->fixture = sys_get_temp_dir().'/homya-ux-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->fixture.'/products', 0700, true);
        File::makeDirectory($this->fixture.'/localizations', 0700, true);
        config(['catalogue.source' => 'files', 'catalogue.archive_path' => $this->fixture.'/index.json',
            'catalogue.products_path' => $this->fixture.'/products', 'catalogue.localizations_path' => $this->fixture.'/localizations',
            'catalogue.media_overrides_path' => $this->fixture.'/missing-overrides.json']);
        $products = [];
        $strings = ['Catégorie UX' => 'UX category', 'Description UX' => 'UX description'];
        for ($i = 1; $i <= 49; $i++) {
            $product = ['slug' => 'ux-produit-'.$i, 'name' => 'Produit UX '.$i, 'brand' => 'Marque UX',
                'reference' => 'UX-'.$i, 'category' => ['slug' => 'ux-categorie', 'name' => 'Catégorie UX'],
                'universe' => 'appliances', 'condition' => 'new', 'price_cents' => 12000 + $i,
                'currency' => 'EUR', 'image' => null, 'images' => [], 'description' => 'Description UX',
                'issues' => [], 'variants' => [], 'has_variants' => false, 'specifications' => []];
            $products[] = $product;
            $strings[$product['name']] = 'UX product '.$i;
            File::put($this->fixture.'/products/'.$product['slug'].'.json', json_encode($product));
        }
        File::put($this->fixture.'/index.json', json_encode(['meta' => ['complete' => true], 'products' => $products]));
        foreach (['en', 'de'] as $locale) {
            File::put($this->fixture.'/localizations/'.$locale.'.json', json_encode(['version' => 1, 'strings' => $strings]));
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixture);
        parent::tearDown();
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($dom);
    }

    public function test_filter_error_preserves_scalar_inputs_and_associates_price_errors(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $query = ['q' => 'UX', 'brand' => 'Marque UX', 'sort' => 'price_desc', 'min' => '200,00', 'max' => '100.00'];
            $response = $this->get('/'.$locale.'/catalogue?'.http_build_query($query))->assertStatus(422);
            $xpath = $this->xpath($response->getContent());
            foreach (['q' => 'filter-query', 'min' => 'filter-min', 'max' => 'filter-max'] as $key => $id) {
                $this->assertSame($query[$key], $xpath->query('//input[@id="'.$id.'"]')->item(0)->getAttribute('value'));
            }
            foreach (['min', 'max'] as $key) {
                $input = $xpath->query('//input[@id="filter-'.$key.'"]')->item(0);
                $this->assertSame('true', $input->getAttribute('aria-invalid'));
                $this->assertSame('filter-errors', $input->getAttribute('aria-describedby'));
            }
            $this->assertSame('price_desc', $xpath->query('//select[@name="sort"]/option[@selected]')->item(0)->getAttribute('value'));
            $this->assertSame('Marque UX', $xpath->query('//select[@name="brand"]/option[@selected]')->item(0)->textContent);
            $response->assertSee(__('catalogue.invalid_filters', [], $locale));
        }
    }

    public function test_malformed_array_is_omitted_while_safe_values_remain_and_markup_is_escaped(): void
    {
        $response = $this->get('/fr/catalogue?'.http_build_query(['q' => '<script>alert(1)</script>', 'brand' => ['unsafe'], 'min' => '1.123']))->assertStatus(422);
        $response->assertDontSee('<script>alert(1)</script>', false);
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('<script>alert(1)</script>', $xpath->query('//input[@id="filter-query"]')->item(0)->getAttribute('value'));
        $this->assertSame('1.123', $xpath->query('//input[@id="filter-min"]')->item(0)->getAttribute('value'));
        $this->assertSame(0, $xpath->query('//select[@name="brand"]/option[@selected]')->length);
        $response = $this->get('/fr/catalogue?'.http_build_query(['q' => str_repeat('a', 500)]))->assertStatus(422);
        $this->assertSame(120, strlen($this->xpath($response->getContent())->query('//input[@id="filter-query"]')->item(0)->getAttribute('value')));
    }

    public function test_card_detail_back_and_language_links_keep_filters_sort_and_page(): void
    {
        $context = ['q' => 'UX', 'brand' => 'Marque UX', 'min' => '100', 'max' => '150', 'sort' => 'price_desc', 'page' => '2'];
        $response = $this->get('/fr/catalogue?'.http_build_query($context))->assertOk();
        $link = $this->xpath($response->getContent())->query('//a[@class="product-card-link"]')->item(0)->getAttribute('href');
        parse_str(parse_url($link, PHP_URL_QUERY), $actual);
        $this->assertSame($context, $actual);
        $productPath = parse_url($link, PHP_URL_PATH);
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get(preg_replace('#^/fr/#', '/'.$locale.'/', $productPath).'?'.http_build_query($context))->assertOk();
            $xpath = $this->xpath($response->getContent());
            foreach ($xpath->query('//a[@data-language-link]|//section[@class="product-summary"]/a[@class="button-primary"]') as $anchor) {
                $url = $anchor->getAttribute('href');
                parse_str(parse_url($url, PHP_URL_QUERY), $actual);
                $this->assertSame($context, $actual);
                if ($anchor->getAttribute('class') === 'button-primary') {
                    $this->assertSame('/'.$locale.'/catalogue', parse_url($url, PHP_URL_PATH));
                    $this->assertSame('results-heading', parse_url($url, PHP_URL_FRAGMENT));
                }
            }
            $this->assertSame('UX', $xpath->query('//input[@id="catalogue-search"]')->item(0)->getAttribute('value'));
        }
    }

    public function test_navigation_context_rejects_external_return_urls_nested_data_and_invalid_range(): void
    {
        $response = $this->get('/fr/produit/ux-produit-1?'.http_build_query([
            'q' => ['nested'], 'return' => 'https://other.example', 'category' => '../unsafe',
            'sort' => 'wrong', 'min' => '200', 'max' => '100', 'page' => '0', 'brand' => 'Marque UX',
        ]))->assertOk();
        foreach ($this->xpath($response->getContent())->query('//a[@data-language-link]') as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $actual);
            $this->assertSame(['brand' => 'Marque UX'], $actual);
        }
        $response->assertDontSee('other.example', false);
    }

    public function test_active_filter_removal_keeps_other_filters_but_resets_pagination(): void
    {
        $response = $this->get('/fr/catalogue?'.http_build_query(['q' => 'UX', 'brand' => 'Marque UX', 'sort' => 'price_desc', 'page' => '2']))->assertOk();
        $anchors = $this->xpath($response->getContent())->query('//div[@class="active-filters"]//a');
        $this->assertSame(2, $anchors->length);
        foreach ($anchors as $link) {
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            $this->assertArrayNotHasKey('page', $query);
            $this->assertSame('price_desc', $query['sort']);
            $this->assertCount(2, $query);
            $this->assertSame('results-heading', parse_url($link->getAttribute('href'), PHP_URL_FRAGMENT));
        }
        // Equal bounds are allowed by the server and by the localized guidance.
        $bounded = $this->get('/fr/catalogue?min=120.01&max=120,01')->assertOk();
        $this->assertSame('1 produit', $this->xpath($bounded->getContent())->query('//*[@id="results-heading"]')->item(0)->textContent);
    }
}
