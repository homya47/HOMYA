<?php

namespace App\Services;

final class CatalogueProductCorrections
{
    // DECISION-050: Bosch EU datasheet KGN36NWEA (06.12.2025), consulted 08.10.2026.
    // https://media3.bosch-home.com/Documents/eudatasheet/de-AT/KGN36NWEA.pdf
    // Public projection only: the immutable source archive remains unchanged.
    private const SOURCE_NAME = 'Réfrigérateur congélateur bas BOSCH 302L No Frost Blanc KGN36NWEA';

    private const NAMES = [
        'fr' => self::SOURCE_NAME,
        'en' => 'Fridge-freezer with bottom freezer BOSCH 302L No Frost White KGN36NWEA',
        'de' => 'Kühl-Gefrierkombination mit Gefrierteil unten BOSCH 302L No Frost Weiß KGN36NWEA',
    ];

    public static function apply(array $source, array $product, string $locale): array
    {
        if (! isset(self::NAMES[$locale])
            || ($source['slug'] ?? null) !== 'froid-kgn36nwea-bosch-combines-congelateur-en-bas'
            || ($source['reference'] ?? null) !== 'KGN36NWEA'
            || ($source['name'] ?? null) !== self::SOURCE_NAME
            || ($product['name'] ?? null) !== (self::NAMES[$locale] ?? null)) {
            return $product;
        }

        $totalPosition = null;
        if (array_key_exists('specifications', $source)) {
            $totals = array_filter($source['specifications'], static fn (array $spec): bool => ($spec['label'] ?? null) === 'Volume utile total (en litres)');
            if (count($totals) !== 1) {
                return $product;
            }
            $position = array_key_first($totals);
            if ($totals[$position]['value'] !== '302' || ($product['specifications'][$position]['value'] ?? null) !== '302') {
                return $product;
            }
            $totalPosition = $position;
        }

        $product['name'] = str_replace('302L', '305L', $product['name']);
        if ($totalPosition !== null) {
            $product['specifications'][$totalPosition]['value'] = '305';
        }

        return $product;
    }
}
