<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class CataloguePresentationTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        DB::shouldReceive('connection')->never();
        $this->fixture = sys_get_temp_dir().'/homya-presentation-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->fixture.'/products', 0700, true);
        File::makeDirectory($this->fixture.'/localizations', 0700, true);
        config(['catalogue.source' => 'files', 'catalogue.archive_path' => $this->fixture.'/index.json',
            'catalogue.products_path' => $this->fixture.'/products', 'catalogue.localizations_path' => $this->fixture.'/localizations',
            'catalogue.media_overrides_path' => $this->fixture.'/absent.json']);
        $products = [];
        $translations = [
            'en' => ['Froid test' => 'Test refrigeration', 'Autre rayon' => 'Other department', 'Description réelle' => 'Actual description', 'Largeur' => 'Width', '60 cm' => '60 cm', 'Blanc' => 'White'],
            'de' => ['Froid test' => 'Testkühlgeräte', 'Autre rayon' => 'Anderer Bereich', 'Description réelle' => 'Vorhandene Beschreibung', 'Largeur' => 'Breite', '60 cm' => '60 cm', 'Blanc' => 'Weiß'],
        ];
        for ($i = 1; $i <= 8; $i++) {
            $product = [
                'slug' => 'presentation-'.$i, 'name' => 'Produit français '.$i, 'brand' => 'Marque test', 'reference' => 'P-'.$i,
                'category' => ['slug' => $i === 8 ? 'other-category' : 'test-cooling', 'name' => $i === 8 ? 'Autre rayon' : 'Froid test'],
                'universe' => $i === 8 ? 'heating' : 'appliances', 'condition' => 'new', 'price_cents' => 12300 + $i,
                'currency' => 'EUR', 'image' => null, 'images' => [], 'issues' => [],
                'description' => $i === 1 ? 'Description réelle' : '',
                'specifications' => $i === 1 ? [['label' => 'Largeur', 'value' => '60 cm']] : [],
                'has_variants' => $i === 1, 'variants' => $i === 1 ? [['title' => 'Blanc', 'sku' => 'P-1-WHITE']] : [],
                'source_url' => 'https://www.damdam.fr/private-history',
            ];
            $products[] = $product;
            $translations['en'][$product['name']] = 'English product '.$i;
            $translations['de'][$product['name']] = 'Deutsches Produkt '.$i;
            File::put($this->fixture.'/products/'.$product['slug'].'.json', json_encode($product));
        }
        File::put($this->fixture.'/index.json', json_encode(['meta' => ['complete' => true], 'products' => $products]));
        foreach ($translations as $locale => $strings) {
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
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }

    public function test_department_intro_and_category_navigation_only_use_real_localized_products(): void
    {
        foreach (['fr' => 'Froid test', 'en' => 'Test refrigeration', 'de' => 'Testkühlgeräte'] as $locale => $category) {
            $response = $this->get('/'.$locale.'/catalogue?universe=appliances')->assertOk()->assertSee($category)
                ->assertDontSee('damdam', false)->assertDontSee('[0,Inf]', false);
            $xpath = $this->xpath($response->getContent());
            $this->assertSame(__('home.categories.appliances.name', [], $locale), $xpath->query('//h1')->item(0)->textContent);
            $this->assertSame(7, $response->viewData('catalogueIntro')['count']);
            $this->assertSame(['test-cooling'], $response->viewData('browseCategories')->pluck('slug')->all());
            $link = $xpath->query('//a[@class="category-entry"]')->item(0);
            $this->assertNotNull($link);
            $this->assertSame('/'.$locale.'/catalogue', parse_url($link->getAttribute('href'), PHP_URL_PATH));
            parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
            $this->assertSame(['category' => 'test-cooling', 'universe' => 'appliances'], $query);
            $this->assertSame(7, $xpath->query('//article[@class="product-card"]')->length);
            if ($locale !== 'fr') {
                $response->assertDontSee('Froid test')->assertDontSee('Produit français');
            }
        }
    }

    public function test_category_counts_render_singular_and_plural_without_selector_syntax(): void
    {
        foreach (['fr' => ['référence', 'références'], 'en' => ['product', 'products'], 'de' => ['Produkt', 'Produkte']] as $locale => [$singular, $plural]) {
            foreach ([0, 1, 7] as $count) {
                $this->assertSame($count.' '.($count === 1 ? $singular : $plural), trans_choice('catalogue.category_count', $count, ['count' => $count], $locale));
            }
        }
    }

    public function test_catalogue_counts_match_zero_one_and_many_results_in_each_language(): void
    {
        foreach (['fr' => ['produit', 'produits'], 'en' => ['product', 'products'], 'de' => ['Produkt', 'Produkte']] as $locale => [$singular, $plural]) {
            foreach (['?q=not-existing' => 0, '?universe=heating' => 1, '?universe=appliances' => 7] as $query => $count) {
                $response = $this->get('/'.$locale.'/catalogue'.$query)->assertOk();
                $xpath = $this->xpath($response->getContent());
                $this->assertSame($count.' '.($count === 1 ? $singular : $plural), $xpath->query('//*[@id="results-heading"]')->item(0)->textContent);
                $intro = $response->viewData('catalogueIntro');
                $this->assertSame(trans_choice($intro['universe'] ? 'catalogue.department_count' : 'catalogue.total_count', $intro['count'], ['count' => $intro['count']], $locale), $xpath->query('//*[@class="catalogue-total"]')->item(0)->textContent);
                $response->assertDontSee('[0,*]', false)->assertDontSee('{1}', false);
            }
        }
    }

    public function test_invalid_universe_is_not_used_for_intro_navigation_or_image(): void
    {
        $response = $this->get('/fr/catalogue?universe='.urlencode('<img src=x onerror=alert(1)>'))->assertStatus(422)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
        $this->assertSame(null, $response->viewData('catalogueIntro')['universe']);
        $this->assertSame(null, $response->viewData('catalogueIntro')['image']);
        $this->assertCount(0, $response->viewData('browseCategories'));
        $this->assertSame(__('catalogue.title', [], 'fr'), $this->xpath($response->getContent())->query('//h1')->item(0)->textContent);
    }

    public function test_category_options_are_global_but_the_selector_is_scoped_even_when_another_field_is_invalid(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $all = $this->get('/'.$locale.'/catalogue')->assertOk();
            $this->assertSame(['other-category', 'test-cooling'], $all->viewData('categories')->pluck('slug')->sort()->values()->all());
            $options = $all->viewData('categoryOptions')->keyBy('slug');
            $this->assertSame(['appliances'], $options['test-cooling']['universes']);
            $this->assertSame(['heating'], $options['other-category']['universes']);
            $names = $all->viewData('categoryOptions')->pluck('name')->all();
            $ordered = $names;
            sort($ordered);
            $this->assertSame($ordered, $names);
            $scoped = $this->get('/'.$locale.'/catalogue?universe=appliances&min=bad')->assertStatus(422);
            $this->assertSame(['test-cooling'], $scoped->viewData('categories')->pluck('slug')->all());
            $this->assertCount(2, $scoped->viewData('categoryOptions'));
            $this->assertSame('appliances', $scoped->viewData('formValues')['universe']);
            $this->assertSame('bad', $scoped->viewData('formValues')['min']);
            $this->assertSame(['min'], $scoped->viewData('errorFields'));
        }
    }

    public function test_shared_categories_keep_all_real_universe_memberships_and_are_valid_in_each_universe(): void
    {
        $archive = json_decode(File::get($this->fixture.'/index.json'), true);
        $shared = $archive['products'][7];
        $shared['slug'] = 'presentation-9';
        $shared['reference'] = 'P-9';
        $shared['category'] = $archive['products'][0]['category'];
        $archive['products'][] = $shared;
        File::put($this->fixture.'/index.json', json_encode($archive));
        File::put($this->fixture.'/products/presentation-9.json', json_encode($shared));
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach (['appliances' => 7, 'heating' => 1] as $universe => $count) {
                $response = $this->get('/'.$locale.'/catalogue?'.http_build_query(['universe' => $universe, 'category' => 'test-cooling']))->assertOk();
                $this->assertSame($count, $response->viewData('products')->total());
                $this->assertContains('test-cooling', $response->viewData('categories')->pluck('slug')->all());
                $this->assertSame(['appliances', 'heating'], $response->viewData('categoryOptions')->firstWhere('slug', 'test-cooling')['universes']);
                $this->assertFalse($response->viewData('categoryReset'));
            }
        }
    }

    public function test_incompatible_known_category_redirect_removes_only_category_and_page_and_preserves_other_filters(): void
    {
        $query = ['q' => 'P', 'universe' => 'heating', 'category' => 'test-cooling', 'brand' => 'Marque test',
            'condition' => 'new', 'min' => '123.00', 'max' => '200,00', 'sort' => 'price_desc', 'page' => '2'];
        $expected = array_diff_key($query, ['category' => true, 'page' => true]) + ['category_reset' => '1'];
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/catalogue?'.http_build_query($query))->assertStatus(302);
            $location = $response->headers->get('Location');
            $this->assertSame('/'.$locale.'/catalogue', parse_url($location, PHP_URL_PATH));
            parse_str(parse_url($location, PHP_URL_QUERY), $normalized);
            $this->assertSame($expected, $normalized);
            $normalizedResponse = $this->get(parse_url($location, PHP_URL_PATH).'?'.parse_url($location, PHP_URL_QUERY))->assertOk();
            $this->assertTrue($normalizedResponse->viewData('categoryReset'));
            $this->assertArrayNotHasKey('category_reset', $normalizedResponse->viewData('filters'));
            $this->assertArrayNotHasKey('category_reset', $normalizedResponse->viewData('formValues'));
            $this->assertSame(1, $normalizedResponse->viewData('products')->currentPage());
            $this->assertSame('presentation-8', $normalizedResponse->viewData('products')->first()['slug']);
            $this->assertSame(12308, $normalizedResponse->viewData('products')->first()['price_cents']);
            foreach ($normalizedResponse->viewData('languageLinks') as $link) {
                parse_str(parse_url($link, PHP_URL_QUERY), $languageFilters);
                $this->assertSame(array_diff_key($expected, ['category_reset' => true]), $languageFilters);
            }
            parse_str(parse_url($normalizedResponse->viewData('products')->url(2), PHP_URL_QUERY), $pagination);
            $this->assertArrayNotHasKey('category_reset', $pagination);
            $this->assertArrayNotHasKey('category', $pagination);
            foreach ($normalizedResponse->viewData('activeFilters') as $filter) {
                $this->assertStringNotContainsString('category_reset', $filter['url']);
            }
        }
    }

    public function test_unknown_categories_and_other_invalid_inputs_are_not_silently_normalized(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            foreach ([['category' => 'unknown-category'], ['category' => 'test-cooling', 'sort' => 'bad'],
                ['category' => 'test-cooling', 'min' => '200', 'max' => '100']] as $query) {
                $response = $this->get('/'.$locale.'/catalogue?'.http_build_query(['universe' => 'heating'] + $query))->assertStatus(422);
                $this->assertFalse($response->headers->has('Location'));
                $this->assertSame($query['category'], $response->viewData('formValues')['category']);
                $this->assertSame(['other-category'], $response->viewData('categories')->pluck('slug')->all());
                $this->assertFalse($response->viewData('categoryReset'));
            }
            $invalidUniverse = $this->get('/'.$locale.'/catalogue?universe=bad&category=test-cooling')->assertStatus(422);
            $this->assertCount(2, $invalidUniverse->viewData('categories'));
        }
    }

    public function test_category_reset_notice_requires_the_exact_marker_and_a_valid_universe(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $this->assertFalse($this->get('/'.$locale.'/catalogue?category_reset=1')->assertOk()->viewData('categoryReset'));
            $this->assertFalse($this->get('/'.$locale.'/catalogue?universe=bad&category_reset=1')->assertStatus(422)->viewData('categoryReset'));
            foreach (['category_reset=2', 'category_reset[]=1'] as $query) {
                $response = $this->get('/'.$locale.'/catalogue?universe=appliances&'.$query)->assertStatus(422);
                $this->assertFalse($response->viewData('categoryReset'));
            }
            $response = $this->get('/'.$locale.'/catalogue?universe=appliances&category_reset=1')->assertOk();
            $this->assertTrue($response->viewData('categoryReset'));
            $this->assertSame(['universe' => 'appliances'], $response->viewData('filters'));
        }
    }

    public function test_product_sections_and_four_related_products_keep_language_values_and_navigation_context(): void
    {
        $archiveHash = hash_file('sha256', $this->fixture.'/index.json');
        $context = ['q' => 'P', 'sort' => 'price_desc', 'page' => '2'];
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/produit/presentation-1?'.http_build_query($context))->assertOk()
                ->assertSee('P-1-WHITE')->assertSee('60 cm')->assertSee(Catalogue::money(12301, $locale));
            $related = $response->viewData('relatedProducts');
            $this->assertCount(4, $related);
            $this->assertSame(['presentation-2', 'presentation-3', 'presentation-4', 'presentation-5'], $related->pluck('slug')->all());
            $this->assertSame(['test-cooling'], $related->pluck('category.slug')->unique()->values()->all());
            $xpath = $this->xpath($response->getContent());
            $navigation = $xpath->query('//nav[@class="product-section-navigation"]/a');
            $this->assertSame(4, $navigation->length);
            foreach ($navigation as $link) {
                $id = ltrim($link->getAttribute('href'), '#');
                $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length);
            }
            $links = $xpath->query('//section[@id="related-products"]//article/a');
            $this->assertSame(4, $links->length);
            foreach ($links as $link) {
                $this->assertStringStartsWith('/'.$locale.'/produit/', parse_url($link->getAttribute('href'), PHP_URL_PATH));
                parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
                $this->assertSame($context, $query);
            }
            $response->assertDontSee('damdam', false);
            if ($locale !== 'fr') {
                $response->assertDontSee('Produit français')->assertDontSee('Description réelle');
            }
        }
        $this->assertSame($archiveHash, hash_file('sha256', $this->fixture.'/index.json'));
    }

    public function test_missing_content_and_empty_category_do_not_create_section_shortcuts_or_other_category_recommendations(): void
    {
        foreach (['fr', 'en', 'de'] as $locale) {
            $response = $this->get('/'.$locale.'/produit/presentation-8')->assertOk()->assertSee(__('catalogue.related_empty', [], $locale));
            $this->assertCount(0, $response->viewData('relatedProducts'));
            $xpath = $this->xpath($response->getContent());
            $this->assertSame(0, $xpath->query('//nav[@class="product-section-navigation"]|//section[@id="related-products"]//article')->length);
            $this->assertSame(0, $xpath->query('//*[@id="product-description"]|//*[@id="product-variants"]')->length);
        }
    }

    public function test_incomplete_related_translation_is_explicit_and_never_mixes_french_recommendations(): void
    {
        $file = $this->fixture.'/localizations/en.json';
        $strings = json_decode(File::get($file), true);
        unset($strings['strings']['Produit français 2']);
        File::put($file, json_encode($strings));
        $response = $this->get('/en/produit/presentation-1')->assertOk()->assertSee('English product 1')
            ->assertSee(__('catalogue.related_unavailable', [], 'en'))->assertDontSee('Produit français');
        $this->assertTrue($response->viewData('relatedUnavailable'));
        $this->assertCount(0, $response->viewData('relatedProducts'));
        $this->get('/en/catalogue')->assertStatus(503)->assertSee(__('catalogue.translation_catalogue_unavailable', [], 'en'));
    }

    public function test_source_failure_during_related_loading_returns_the_safe_unavailable_page(): void
    {
        $catalogue = Mockery::mock(Catalogue::class);
        $catalogue->shouldReceive('product')->once()->with('presentation-1')
            ->andReturn(json_decode(File::get($this->fixture.'/products/presentation-1.json'), true));
        $catalogue->shouldReceive('sourceUnavailable')->twice()->andReturn(false, true);
        $catalogue->shouldReceive('products')->once()->andReturn(collect());
        $this->app->instance(Catalogue::class, $catalogue);
        $this->get('/fr/produit/presentation-1')->assertStatus(503)
            ->assertSee(__('catalogue.source_unavailable', [], 'fr'))
            ->assertDontSee('Produit français')->assertDontSee('damdam', false);
    }
}
