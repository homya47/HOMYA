<?php

// OP-10 authorised offline test. No native SQL, secret loader, service or active source change.
use App\Services\Catalogue;
use App\Services\CatalogueArchivePayload;
use App\Services\CatalogueReadProfile;
use App\Services\FileCatalogueSource;
use App\Services\SqlCatalogueSource;
use App\Services\StrictJson;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\CatalogueReadConnectionDouble;

umask(0077);
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
DB::shouldReceive('connection')->never();
DB::shouldReceive('connectUsing')->never();
if (config('catalogue.source') !== 'files' || $app->configurationIsCached()) {
    throw new RuntimeException('OFFLINE_ACTIVE_STATE_UNEXPECTED');
}
$start = hrtime(true);
$rows = json_decode(file_get_contents(base_path('output/qa-import/op10-profil-rows-prives.json')), true, flags: JSON_THROW_ON_ERROR);
$payload = CatalogueArchivePayload::fromStoredRows(...$rows);
$payload->assertVerified($rows[0]);
if ($payload->digest() !== CatalogueReadProfile::PAYLOAD || $payload->lotKey() !== CatalogueReadProfile::LOT) {
    throw new RuntimeException('OFFLINE_PAYLOAD_DIVERGENT');
}
$profile = CatalogueReadProfile::loadApproved();
if (! StrictJson::same(StrictJson::decode(StrictJson::encode(CatalogueReadProfile::fromPayload($payload)->data), false), StrictJson::decode(StrictJson::encode($profile->data), false))) {
    throw new RuntimeException('OFFLINE_PROFILE_DIVERGENT');
}
$db = new CatalogueReadConnectionDouble(['archive_lots' => [$rows[0]], 'archive_categories' => $rows[1], 'archive_products' => $rows[2], 'archive_translations' => $rows[3]]);
unset($rows, $payload);
$locales = [];
foreach (['fr', 'en', 'de'] as $locale) {
    app()->setLocale($locale);
    $sql = new Catalogue(new SqlCatalogueSource(fn () => $db, $profile));
    $file = new Catalogue(new FileCatalogueSource);
    $begin = hrtime(true);
    $cards = $file->products()->all();
    if (count($cards) !== 3709 || $sql->products()->all() !== $cards || $sql->archive() !== $file->archive()) {
        throw new RuntimeException('OFFLINE_CARDS_DIVERGENT');
    }
    $detailHash = hash_init('sha256');
    foreach ($cards as $card) {
        $expected = $file->product($card['slug']);
        $actual = $sql->product($card['slug']);
        if ($actual === null || $actual !== $expected) {
            throw new RuntimeException('OFFLINE_DETAIL_DIVERGENT');
        }
        hash_update($detailHash, StrictJson::encode($actual));
    }
    $filters = [[], ['sort' => 'price_asc'], ['sort' => 'price_desc'], ['q' => 'poêle'], ['q' => 'Bosch'],
        ['universe' => 'appliances', 'min' => '100', 'max' => '5000', 'sort' => 'price_desc'], ['q' => 'no-possible-result-123456789']];
    foreach ($filters as $filter) {
        $a = $file->filtered($filter);
        $b = $sql->filtered($filter);
        if ($a->all() !== $b->all()) {
            throw new RuntimeException('OFFLINE_FILTER_DIVERGENT');
        }
        $last = max(1, (int) ceil($a->count() / 24));
        foreach (array_unique([1, max(1, (int) ceil($last / 2)), $last]) as $page) {
            if ($a->forPage($page, 24)->values()->all() !== $b->forPage($page, 24)->values()->all()) {
                throw new RuntimeException('OFFLINE_PAGE_DIVERGENT');
            }
        }
    }
    $locales[$locale] = ['cards' => count($cards), 'details' => count($cards), 'filter_cases' => count($filters),
        'cards_sha256' => hash('sha256', StrictJson::encode($cards)), 'details_sha256' => hash_final($detailHash),
        'seconds' => (hrtime(true) - $begin) / 1e9];
    unset($sql, $file, $cards, $a, $b);
}
$report = ['status' => 'OFFLINE_EXHAUSTIVE_PARITY_VERIFIED', 'decision' => 'DECISION-042',
    'lot_key' => CatalogueReadProfile::LOT, 'payload_sha256' => CatalogueReadProfile::PAYLOAD, 'profile_sha256' => CatalogueReadProfile::PROFILE,
    'fixture_source' => 'Pure capsule-derived rows with synthetic dates; not a database dump', 'locales' => $locales,
    'simulated_selects' => count($db->operations), 'seconds' => (hrtime(true) - $start) / 1e9,
    'peak_bytes' => memory_get_peak_usage(true), 'memory_limit' => ini_get('memory_limit'),
    'sql_executed' => false, 'dedicated_credentials_read' => false, 'active_source' => 'files', 'activation' => false,
    'native_http_performance_verified' => false];
file_put_contents(base_path('output/qa-import/op10-parite-exhaustive-hors-base.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
echo json_encode($report)."\n";
