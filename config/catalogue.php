<?php

return [
    'source' => env('HOMYA_CATALOGUE_SOURCE', 'files'),
    // Read-only collection archive for local preview. XAMPP remains the database target.
    'archive_path' => storage_path('app/catalogue/index.json'),
    'products_path' => storage_path('app/catalogue/products'),
    'localizations_path' => storage_path('app/catalogue/localizations'),
    'media_overrides_path' => storage_path('app/catalogue/media-overrides.json'),
    // Separate private archive preparation; does not change the file-backed catalogue source.
    'capsule_parent_path' => storage_path('app/private/catalogue-archives'),
    'capsule_review_reports' => ['synthese.json', 'anomalies-detaillees.json', 'empreintes-sources.json'],
];
