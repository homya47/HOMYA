<?php

namespace Tests\Unit;

use App\Services\CatalogueContent;
use PHPUnit\Framework\TestCase;

class CatalogueContentTest extends TestCase
{
    public function test_exact_duplicate_specs_are_collapsed_but_different_values_require_review(): void
    {
        $source = ['slug' => 'fixture', 'name' => 'Fixture', 'specifications' => [
            ['label' => 'Poids', 'value' => '12 kg'], ['label' => 'Poids', 'value' => '12 kg'],
            ['label' => 'Largeur', 'value' => '20 cm'], ['label' => 'Largeur', 'value' => '30 cm'],
        ]];
        $product = ['name' => 'Fixture', 'description' => 'Description', 'reference' => 'REF',
            'specifications' => $source['specifications'], 'issues' => ['conflicting_specification']];
        $result = CatalogueContent::apply($source, $product, 'fr');
        $this->assertCount(2, $result['specifications']);
        $this->assertSame('12 kg', $result['specifications'][0]['value']);
        $this->assertSame('En cours de vérification', $result['specifications'][1]['value']);
        $this->assertSame(['20 cm', '30 cm'], $result['specification_reviews'][0]['values']);
        $this->assertContains('conflicting_specification', $result['issues']);
        $this->assertSame($source['specifications'], $product['specifications']);
    }

    public function test_impossible_power_interval_is_flagged_without_guessing_decimal_or_changing_source(): void
    {
        $specs = [['label' => 'Puissance min (kW)', 'value' => '24'], ['label' => 'Puissance max (kW)', 'value' => '6.5']];
        $source = ['slug' => 'fixture', 'name' => 'Fixture', 'specifications' => $specs];
        $product = ['name' => 'Fixture', 'description' => 'Description', 'reference' => 'REF', 'specifications' => $specs, 'issues' => []];
        $result = CatalogueContent::apply($source, $product, 'en');
        $this->assertContains('inconsistent_power_interval', $result['issues']);
        $this->assertSame(['Awaiting verification', 'Awaiting verification'], array_column($result['specifications'], 'value'));
        $this->assertSame($specs, $source['specifications']);
        $source['specifications'][0]['value'] = '2.4';
        $product['specifications'] = $source['specifications'];
        $this->assertSame([], CatalogueContent::apply($source, $product, 'en')['issues']);
    }
}
