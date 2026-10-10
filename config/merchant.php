<?php

return [
    'enabled' => env('HOMYA_MERCHANT_FEED_ENABLED', false),
    // Merchant validation is separate from catalogue publication. No private record
    // or diagnostic is exposed through the public XML endpoint.
    'reviews_path' => storage_path('app/private/google/merchant-products.json'),
];
