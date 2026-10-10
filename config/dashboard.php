<?php

return [
    'enabled' => env('HOMYA_ADMIN_MANAGEMENT_ENABLED', false),
    'google' => [
        'client_id' => env('HOMYA_GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('HOMYA_GOOGLE_CLIENT_SECRET', ''),
        'refresh_token' => env('HOMYA_GOOGLE_REFRESH_TOKEN', ''),
        'ga4' => env('HOMYA_GA4_PROPERTY_ID', ''),
        'search_console' => env('HOMYA_GSC_SITE', ''),
        'ads' => env('HOMYA_GOOGLE_ADS_CUSTOMER_ID', ''),
        'ads_manager' => env('HOMYA_GOOGLE_ADS_MANAGER_ID', ''),
        'developer_token' => env('HOMYA_GOOGLE_ADS_DEVELOPER_TOKEN', ''),
        'merchant' => env('HOMYA_MERCHANT_ACCOUNT_ID', ''),
        'gtm' => env('HOMYA_GTM_CONTAINER_ID', ''),
    ],
    'meta' => ['account' => env('HOMYA_META_ACCOUNT_ID', ''), 'token' => env('HOMYA_META_ACCESS_TOKEN', ''), 'version' => env('HOMYA_META_API_VERSION', '')],
];
