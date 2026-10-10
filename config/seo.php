<?php

return [
    // Enable only after the public HTTPS domain and production checks are complete.
    'indexing_enabled' => env('HOMYA_SEO_INDEXING_ENABLED', false),
    'public_url' => env('HOMYA_PUBLIC_URL', ''),
    'verification' => env('HOMYA_GOOGLE_SITE_VERIFICATION', ''),
    'sitemap_chunk_size' => 1000,
];
