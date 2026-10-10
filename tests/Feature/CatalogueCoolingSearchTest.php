<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use App\Services\CatalogueSource;
use App\Services\FileCatalogueSource;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogueCoolingSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['catalogue.source' => 'files']);
        DB::shouldReceive('connection')->never();
    }

    public function test_refrigerator_aliases_keep_the_complete_real_archive_selection_in_every_language(): void
    {
        $paths = [config('catalogue.archive_path'), config('catalogue.localizations_path').'/en.json', config('catalogue.localizations_path').'/de.json'];
        $hashes = array_map(fn ($path) => hash_file('sha256', $path), $paths);
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $catalogue = new Catalogue(new FileCatalogueSource);
            $slugs = static fn ($products) => $products->pluck('slug')->sort()->values()->all();
            $canonical = $slugs($catalogue->filtered(['universe' => 'appliances', 'q' => 'réfrigérateur']));
            $this->assertNotEmpty($canonical);
            foreach (['Refrigerators', 'Kühlschränke'] as $alias) {
                $this->assertSame($canonical, $slugs($catalogue->filtered(['universe' => 'appliances', 'q' => $alias])));
            }
            $this->assertFalse($catalogue->sourceUnavailable());
            $this->assertFalse($catalogue->translationUnavailable());
        }
        $this->assertSame($hashes, array_map(fn ($path) => hash_file('sha256', $path), $paths));
    }

    public function test_brand_search_and_longer_user_phrases_are_not_rewritten(): void
    {
        $names = ['Réfrigérateur blanc', 'Réfrigérateur compact', 'Lave-linge Bosch', 'Refrigerators spare'];
        $products = [];
        foreach ($names as $position => $name) {
            $products[] = ['slug' => 'test-'.($position + 1), 'name' => $name, 'brand' => in_array($position, [0, 2], true) ? 'Bosch' : 'Other',
                'reference' => 'TEST-'.($position + 1), 'category' => ['slug' => 'test-category', 'name' => 'Appareils'],
                'universe' => 'appliances', 'condition' => 'new', 'price_cents' => null, 'currency' => 'EUR',
                'has_variants' => false, 'issues' => [], 'image' => null, 'images' => []];
        }
        $dictionary = ['en' => ['Appareils' => 'Appliances', 'Réfrigérateur blanc' => 'White refrigerator',
            'Réfrigérateur compact' => 'Compact refrigerator', 'Lave-linge Bosch' => 'Bosch washing machine', 'Refrigerators spare' => 'Refrigerators spare'],
            'de' => ['Appareils' => 'Haushaltsgeräte', 'Réfrigérateur blanc' => 'Weißer Kühlschrank',
                'Réfrigérateur compact' => 'Kompakter Kühlschrank', 'Lave-linge Bosch' => 'Bosch Waschmaschine', 'Refrigerators spare' => 'Refrigerators spare']];
        $source = new class($products, $dictionary) implements CatalogueSource
        {
            public function __construct(private array $products, private array $dictionaries) {}

            public function archive(): array
            {
                return ['meta' => ['complete' => true], 'products' => $this->products];
            }

            public function product(string $slug): ?array
            {
                return collect($this->products)->firstWhere('slug', $slug);
            }

            public function dictionary(string $locale): array
            {
                return $this->dictionaries[$locale] ?? [];
            }

            public function mediaOverrides(): array
            {
                return [];
            }

            public function prefetchProducts(array $slugs): void {}
        };
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $catalogue = new Catalogue($source);
            $this->assertSame(['test-1', 'test-3'], $catalogue->filtered(['q' => 'Bosch'])->pluck('slug')->sort()->values()->all());
            $this->assertSame(['test-4'], $catalogue->filtered(['q' => 'Refrigerators spare'])->pluck('slug')->all());
        }
    }
}
