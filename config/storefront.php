<?php

return [
    'categories' => [
        ['key' => 'catalogue', 'filters' => []],
        ['key' => 'heating', 'filters' => ['universe' => 'heating']],
        ['key' => 'firewood', 'filters' => ['universe' => 'firewood']],
        ['key' => 'furniture', 'filters' => ['universe' => 'furniture']],
        ['key' => 'cooling', 'filters' => ['universe' => 'appliances'], 'query_translation' => 'home.cooling_search'],
        ['key' => 'washing_lg', 'filters' => ['category' => 'lavage-lave-linge', 'brand' => 'LG']],
        ['key' => 'coffee_philips', 'filters' => ['category' => 'cafe-baristina', 'brand' => 'Philips']],
        ['key' => 'televisions_lg', 'filters' => ['universe' => 'televisions', 'brand' => 'LG']],
    ],
    // Literal French searches also match the intact source titles in EN/DE.
    'discoveries' => [
        ['key' => 'extendable_tables', 'filters' => ['category' => 'mobilier-tables-extensibles']],
        ['key' => 'ceramic_tables', 'filters' => ['category' => 'mobilier-tables-extensibles', 'q' => 'céramique']],
        ['key' => 'tables_under_800', 'filters' => ['category' => 'mobilier-tables-extensibles', 'max' => '800']],
        ['key' => 'etna', 'filters' => ['universe' => 'heating', 'brand' => 'ETNA']],
        ['key' => 'nordica', 'filters' => ['universe' => 'heating', 'q' => 'La Nordica']],
        ['key' => 'wood_under_1000', 'filters' => ['category' => 'chauffage-poeles-a-bois', 'max' => '1000']],
        ['key' => 'tables_160', 'filters' => ['category' => 'mobilier-tables-extensibles', 'q' => '160 cm']],
        ['key' => 'white_tables', 'filters' => ['category' => 'mobilier-tables-extensibles', 'q' => 'blanc']],
        ['key' => 'pellet_under_1500', 'filters' => ['universe' => 'heating', 'q' => 'poêle à granulés', 'max' => '1500']],
    ],
];
