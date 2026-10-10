<?php

// File-backed profiling and parity fingerprints; no dedicated access or SQL.
use App\Services\Catalogue;
use App\Services\FileCatalogueSource;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$report = [];
foreach (['fr', 'en', 'de'] as $locale) {
    app()->setLocale($locale);
    $catalogue = new Catalogue(new FileCatalogueSource);
    $start = hrtime(true);
    $products = $catalogue->products();
    $report[$locale]['projection_seconds'] = (hrtime(true) - $start) / 1e9;
    $report[$locale]['products'] = $products->count();
    foreach ([[], ['sort' => 'price_asc'], ['sort' => 'price_desc'], ['q' => 'Bosch'], ['universe' => 'heating'], ['min' => '1000', 'max' => '2000']] as $filters) {
        $start = hrtime(true);
        $items = $catalogue->filtered($filters);
        $report[$locale]['filters'][] = ['filters' => $filters, 'seconds' => (hrtime(true) - $start) / 1e9,
            'count' => $items->count(), 'sha256' => hash('sha256', json_encode($items->all(), JSON_THROW_ON_ERROR))];
    }
}
echo json_encode(['sql_executed' => false, 'locales' => $report, 'peak_bytes' => memory_get_peak_usage(true)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
