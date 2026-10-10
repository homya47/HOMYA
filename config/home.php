<?php

return [
    'departments' => [
        ['key' => 'heating', 'id' => 'universe-heating', 'image' => 'chauffage-display.webp', 'width' => 800, 'height' => 800, 'filters' => ['universe' => 'heating']],
        ['key' => 'furniture', 'id' => 'universe-furniture', 'image' => 'mobilier-display.webp', 'width' => 1200, 'height' => 1200, 'filters' => ['universe' => 'furniture']],
        ['key' => 'cooling', 'id' => 'universe-appliances', 'image' => 'froid-display.webp', 'width' => 450, 'height' => 450, 'filters' => ['universe' => 'appliances', 'q' => 'réfrigérateur']],
        ['key' => 'washing', 'id' => 'department-washing', 'image' => 'lavage-display.webp', 'width' => 1044, 'height' => 1334, 'filters' => ['category' => 'lavage-lave-linge']],
        ['key' => 'coffee', 'id' => 'department-coffee', 'image' => 'cafe.webp', 'width' => 1600, 'height' => 1600, 'filters' => ['category' => 'cafe-baristina']],
    ],
    // Stable merchandising order. Every entry must exist in the catalogue;
    // missing entries are omitted without silently substituting another model.
    'featured' => [
        'chauffage-fantea32en-chaudieres',
        'froid-rb33b610esa-samsung-combines-congelateur-en-bas',
        'chauffage-pack-ottimo-poeles-a-granules-etanches',
        'chauffage-pack-eworta11-poeles-a-bois',
        'chauffage-fango8c-conf-poeles-a-granules-canalisables',
        'chauffage-pack-vulcano-12-etanche-up-poeles-a-granules-etanches',
        'chauffage-mini5-conf-poeles-a-granules-classiques',
        'chauffage-etmerviplus8cdce-cuisinieres-a-bois',
        'chauffage-18250-poeles-a-bois',
        'chauffage-hsflaamos-poeles-a-bois',
        'chauffage-pack-thermomagnum-conf-cuisinieres-a-bois',
        'froid-kgv36vbeas-bosch-combines-congelateur-en-bas',
    ],
    'discovery' => [
        'poele-a-bois-raccordable-3-vitres-avec-bucher-firematic-calgary-s-12-5-kw',
        'poele-a-bois-2-vitres-firematic-ottawa-s-12-6-kw',
        'poele-a-bois-bronpi-lira-etanche-en-fonte-5-kw',
        'poele-a-bois-firematic-chicago-11-2-kw',
        'poele-a-bois-3-vitres-raccordable-bronpi-loreto-10-kw',
        'poele-a-bois-raccordable-3-vitres-avec-bucher-firematic-calgary-l-13-5-kw',
        'poele-a-bois-raccordable-avec-bucher-firematic-toronto-s-12-6-kw',
        'poele-a-bois-raccordable-en-fonte-la-nordica-jennifer-16-7-7-kw',
    ],
];
