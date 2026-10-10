<?php

namespace Tests\Unit;

use App\Services\CatalogueProductCorrections;
use PHPUnit\Framework\TestCase;

class CatalogueProductCorrectionsTest extends TestCase
{
    private function source(): array
    {
        return [
            'slug' => 'froid-kgn36nwea-bosch-combines-congelateur-en-bas',
            'reference' => 'KGN36NWEA',
            'name' => 'Réfrigérateur congélateur bas BOSCH 302L No Frost Blanc KGN36NWEA',
            'specifications' => [
                ['label' => 'Volume utile total (en litres)', 'value' => '302'],
                ['label' => 'Capacité congélateur (en L)', 'value' => '89'],
                ['label' => 'Autre valeur', 'value' => '302'],
            ],
        ];
    }

    public function test_verified_capacity_is_projected_in_all_languages_without_changing_other_fields_or_source(): void
    {
        $source = $this->source();
        foreach ([
            'fr' => $source['name'],
            'en' => 'Fridge-freezer with bottom freezer BOSCH 302L No Frost White KGN36NWEA',
            'de' => 'Kühl-Gefrierkombination mit Gefrierteil unten BOSCH 302L No Frost Weiß KGN36NWEA',
        ] as $locale => $name) {
            $product = ['name' => $name, 'price_cents' => 44730, 'images' => ['unchanged'], 'description_hidden' => true,
                'specifications' => [['label' => 'Localized total', 'value' => '302'], ['label' => 'Localized freezer', 'value' => '89'], ['label' => 'Other value', 'value' => '302']]];
            $expected = $product;
            $expected['name'] = str_replace('302L', '305L', $name);
            $expected['specifications'][0]['value'] = '305';
            $this->assertSame($expected, CatalogueProductCorrections::apply($source, $product, $locale));
            $this->assertSame($this->source(), $source);
            $index = $source;
            unset($index['specifications']);
            $this->assertSame(['name' => $expected['name']], CatalogueProductCorrections::apply($index, ['name' => $name], $locale));
        }
    }

    public function test_other_models_changed_sources_or_changed_translations_are_not_overridden(): void
    {
        $source = $this->source();
        $product = ['name' => $source['name'], 'specifications' => $source['specifications']];
        foreach (['slug', 'reference', 'name'] as $field) {
            $changed = $source;
            $changed[$field] .= '-changed';
            $this->assertSame($product, CatalogueProductCorrections::apply($changed, $product, 'fr'));
        }
        $changed = $product;
        $changed['name'] .= ' changed';
        $this->assertSame($changed, CatalogueProductCorrections::apply($source, $changed, 'fr'));
        $this->assertSame($product, CatalogueProductCorrections::apply($source, $product, 'es'));
        $source['specifications'][0]['value'] = '306';
        $this->assertSame($product, CatalogueProductCorrections::apply($source, $product, 'fr'));
        $source['specifications'] = [];
        $this->assertSame($product, CatalogueProductCorrections::apply($source, $product, 'fr'));
    }
}
