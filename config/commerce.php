<?php

return [
    'enabled' => env('HOMYA_CHECKOUT_ENABLED', false),
    'deferred_enabled' => env('HOMYA_DEFERRED_ORDERS_ENABLED', false),
    'inventory_enabled' => env('HOMYA_INVENTORY_ENABLED', false),
    'legal_ready' => env('HOMYA_COMMERCE_LEGAL_READY', false),
    'connection' => 'commerce',
    'countries' => ['FR', 'BE', 'DE'],
    'currency' => 'EUR',
    'shipping_cents' => 0,
    // Technical bounds, not a promise of commercial availability.
    'max_lines' => 20,
    'max_quantity' => 99,
    'max_total_cents' => 99_999_999,
    'bank' => [
        'enabled' => env('HOMYA_BANK_TRANSFER_ENABLED', false),
        'beneficiary' => env('HOMYA_BANK_BENEFICIARY', ''),
        'iban' => env('HOMYA_BANK_IBAN', ''),
        'bic' => env('HOMYA_BANK_BIC', ''),
        'bank_name' => env('HOMYA_BANK_NAME', ''),
    ],
];
