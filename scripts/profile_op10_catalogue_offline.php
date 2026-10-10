<?php

// Offline simulated SELECT profiling. Never opens PDO, a real secret, service or database.
use App\Services\Catalogue;
use App\Services\CatalogueReadProfile;
use App\Services\SqlCatalogueSource;
use App\Services\StrictJson;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\CatalogueReadConnectionDouble;

umask(0077);
require __DIR__.'/../vendor/autoload.php';
$root = dirname(__DIR__);
$dir = $root.'/output/qa-import/op10-profil-lectures-privees';
$mode = $argv[1] ?? '';
if ($mode === 'prepare') {
    if (file_exists($dir)) {
        throw new RuntimeException('OFFLINE_FIXTURE_EXISTS');
    }
    $rows = json_decode(file_get_contents($root.'/output/qa-import/op10-profil-rows-prives.json'), true, flags: JSON_THROW_ON_ERROR);
    mkdir($dir, 0700);
    $files = ['archive_lots' => [$rows[0]], 'archive_categories' => $rows[1],
        'archive_products' => array_map(fn ($r) => array_intersect_key($r, array_flip(CatalogueReadProfile::INDEX_COLUMNS)), $rows[2])];
    foreach (['en', 'de'] as $locale) {
        $files['archive_translations_'.$locale] = array_values(array_filter($rows[3], fn ($r) => $r['locale'] === $locale));
    }
    $hashes = [];
    foreach ($files as $name => $data) {
        $text = StrictJson::encode($data);
        file_put_contents($dir.'/'.$name.'.json', $text);
        $hashes[$name] = hash('sha256', $text);
    }
    file_put_contents($dir.'/hashes.json', StrictJson::encode($hashes));
    echo json_encode(['status' => 'OFFLINE_SELECT_FIXTURE_PREPARED', 'sql_executed' => false])."\n";
    exit;
}
$locale = $argv[2] ?? '';
if ($mode !== 'measure' || ! in_array($locale, ['fr', 'en', 'de'], true)) {
    throw new RuntimeException('OFFLINE_PROFILE_ARGUMENTS_INVALID');
}
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
DB::shouldReceive('connection')->never();
DB::shouldReceive('connectUsing')->never();
if (config('catalogue.source') !== 'files' || $app->configurationIsCached()) {
    throw new RuntimeException('OFFLINE_ACTIVE_STATE_UNEXPECTED');
}
$profile = CatalogueReadProfile::loadApproved();
$hashes = json_decode(file_get_contents($dir.'/hashes.json'), true, flags: JSON_THROW_ON_ERROR);
$load = function ($table, $query, $bindings) use ($dir, $hashes) {
    if ($table === 'archive_products' && str_contains($query, '`detail_document`')) {
        $result = [];
        foreach (array_slice($bindings, 1) as $slug) {
            if (! preg_match('/^[a-z0-9][a-z0-9-]{0,220}$/D', $slug)) {
                throw new RuntimeException('OFFLINE_DETAIL_SLUG_INVALID');
            }
            $text = file_get_contents(storage_path('app/private/catalogue-archives/'.CatalogueReadProfile::LOT.'/files/storage/app/catalogue/products/'.$slug.'.json'));
            $result[] = ['lot_key' => CatalogueReadProfile::LOT, 'slug' => $slug, 'detail_document' => StrictJson::encode(StrictJson::decode($text, false))];
        }

        return $result;
    }
    $name = $table === 'archive_translations' ? $table.'_'.$bindings[1] : $table;
    $text = file_get_contents($dir.'/'.$name.'.json');
    if (hash('sha256', $text) !== $hashes[$name]) {
        throw new RuntimeException('OFFLINE_FIXTURE_DIVERGENT');
    }

    return json_decode($text, true, flags: JSON_THROW_ON_ERROR);
};
$db = new CatalogueReadConnectionDouble(array_fill_keys(['archive_lots', 'archive_categories', 'archive_products', 'archive_translations'], []), $load);
app()->setLocale($locale);
memory_reset_peak_usage();
$start = hrtime(true);
$catalogue = new Catalogue(new SqlCatalogueSource(fn () => $db, $profile));
$cards = $catalogue->filtered(['sort' => 'price_asc']);
$detail = $catalogue->product($cards->first()['slug']);
if ($cards->count() !== 3709 || $detail === null || $catalogue->sourceUnavailable()) {
    throw new RuntimeException('OFFLINE_TARGETED_PROFILE_FAILED');
}
$report = ['status' => 'OFFLINE_TARGETED_READ_VERIFIED', 'locale' => $locale, 'cards' => $cards->count(),
    'seconds' => (hrtime(true) - $start) / 1e9, 'peak_bytes' => memory_get_peak_usage(true), 'memory_limit' => ini_get('memory_limit'),
    'simulated_selects' => count($db->operations), 'simulated_detail_batches' => count(array_filter($db->operations, fn ($op) => str_contains($op['query'], '`detail_document`'))),
    'sql_executed' => false, 'dedicated_credentials_read' => false, 'native_http_performance_verified' => false, 'activation' => false];
file_put_contents($root.'/output/qa-import/op10-profil-cible-'.$locale.'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
echo json_encode($report)."\n";
