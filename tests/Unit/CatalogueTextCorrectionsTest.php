<?php

namespace Tests\Unit;

use App\Services\CatalogueTextCorrections;
use PHPUnit\Framework\TestCase;

class CatalogueTextCorrectionsTest extends TestCase
{
    public function test_only_exact_approved_source_and_translation_are_corrected(): void
    {
        $this->assertSame('Freestanding', CatalogueTextCorrections::apply('Pose libre', 'en', 'Free laying'));
        $this->assertSame('Freistehend', CatalogueTextCorrections::apply('Pose libre', 'de', 'Freilegung'));
        $this->assertSame('Different', CatalogueTextCorrections::apply('Pose libre', 'en', 'Different'));
        $this->assertSame('Free laying', CatalogueTextCorrections::apply('Different', 'en', 'Free laying'));
        $this->assertSame('Pose libre', CatalogueTextCorrections::apply('Pose libre', 'fr', 'Pose libre'));
    }

    public function test_model_and_numerical_values_are_preserved_in_targeted_bosch_title(): void
    {
        $source = 'Réfrigérateur congélateur bas BOSCH 279L No Frost Argent KGN33NSDB';
        $translated = 'Kühl-Gefrierkombination mit Gefrierteil unten BOSCH 279L No Frost Argent KGN33NSDB';
        $this->assertSame(str_replace('Argent', 'Silber', $translated), CatalogueTextCorrections::apply($source, 'de', $translated));
    }

    public function test_sulphur_and_missing_percent_only_are_corrected_without_reconciling_source_contradictions(): void
    {
        $source = '10/20 Anthramix supérieur demi palette de 40 sacs de 25 kg soit 1000 kg (1 Tonne) 50% d’Anthracite et 50% de coke de pétrole Soufre inférieur à 2.4 grammes thermie Palette de 20 sacs de 25 kg = 1000 kg';
        $translated = '10/20 Obere Anthramixhalbpalette von 40 Säcke von 25 kg oder 1000 kg1 Tonnen 50% Anthrazit und 50Petrolkoks Sauerer weniger als 2.4 Gramm thermische Palette von 20 Säcke von 25 kg = 1000 kg';
        $corrected = CatalogueTextCorrections::apply($source, 'de', $translated);
        $this->assertSame(strtr($translated, ['50Petrolkoks' => '50% Petrolkoks', 'Sauerer' => 'Schwefel']), $corrected);
        $this->assertSame(2, substr_count($corrected, '50%'));
        $this->assertStringContainsString('2.4 Gramm', $corrected);
        $this->assertStringContainsString('20 Säcke von 25 kg = 1000 kg', $corrected);
    }

    public function test_selected_heating_titles_translate_residual_words_and_preserve_model_power(): void
    {
        $source = 'Poêle à granulés extra plat Mini · 5 Kw';
        $english = 'Pellet stove extra plat Mini · 5 Kw';
        $this->assertSame('Pellet stove slim Mini · 5 Kw', CatalogueTextCorrections::apply($source, 'en', $english));
        $this->assertSame('Pelletofen besonders flach Mini · 5 Kw', CatalogueTextCorrections::apply($source, 'de', 'Pelletofen extra plat Mini · 5 Kw'));
        $this->assertSame($source, CatalogueTextCorrections::apply($source, 'fr', $source));
        $this->assertSame($english.' changed', CatalogueTextCorrections::apply($source, 'en', $english.' changed'));
        $source = 'Poêle à bois 2 vitres – FIREMATIC Ottawa – S 12.6 kW';
        $this->assertSame('Wood-burning stove 2 glass panels – FIREMATIC Ottawa – S 12.6 kW', CatalogueTextCorrections::apply($source, 'en', 'Wood-burning stove 2 vitres – FIREMATIC Ottawa – S 12.6 kW'));
        $this->assertSame('Holzofen 2 Sichtscheiben – FIREMATIC Ottawa – S 12.6 kW', CatalogueTextCorrections::apply($source, 'de', 'Holzofen 2 vitres – FIREMATIC Ottawa – S 12.6 kW'));
    }
}
