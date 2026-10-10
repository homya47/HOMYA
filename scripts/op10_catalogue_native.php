<?php

/** Native OP-10 parity helper. No bootstrap, dedicated secret or PDO at inclusion/default/refusal. */
use App\Services\ArchiveDatabaseGuard;
use App\Services\Catalogue;
use App\Services\CatalogueArchiveStore;
use App\Services\CatalogueReadProfile;
use App\Services\FileCatalogueSource;
use App\Services\SqlCatalogueSource;
use App\Services\StrictJson;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

/** Pure presentation comparison. Fixtures are injected by tests, never persisted in SQL. */
function op10Compare(Catalogue $file, Catalogue $sql, int $expectedCards): array
{
    $cards = $file->products()->all();
    if (count($cards) !== $expectedCards || $sql->products()->all() !== $cards || $sql->archive() !== $file->archive()) {
        throw new RuntimeException('PUBLIC_CARDS_DIVERGENT');
    }
    $hash = hash_init('sha256');
    foreach ($cards as $card) {
        $expected = $file->product($card['slug']);
        $actual = $sql->product($card['slug']);
        if ($actual === null || $actual !== $expected) {
            throw new RuntimeException('PUBLIC_DETAIL_DIVERGENT');
        }
        hash_update($hash, StrictJson::encode($actual));
    }
    $filters = [[], ['sort' => 'price_asc'], ['sort' => 'price_desc'], ['q' => 'poêle'], ['q' => 'Bosch'],
        ['universe' => 'appliances', 'min' => '100', 'max' => '5000', 'sort' => 'price_desc'], ['q' => 'no-possible-result-123456789']];
    foreach ($filters as $filter) {
        $a = $file->filtered($filter);
        $b = $sql->filtered($filter);
        if ($a->all() !== $b->all()) {
            throw new RuntimeException('PUBLIC_FILTERS_DIVERGENT');
        }
        $last = max(1, (int) ceil($a->count() / 24));
        foreach (array_unique([1, max(1, (int) ceil($last / 2)), $last]) as $page) {
            if ($a->forPage($page, 24)->values()->all() !== $b->forPage($page, 24)->values()->all()) {
                throw new RuntimeException('PUBLIC_PAGES_DIVERGENT');
            }
        }
    }

    return ['cards' => count($cards), 'details' => count($cards), 'filter_cases' => count($filters),
        'cards_sha256' => hash('sha256', StrictJson::encode($cards)), 'details_sha256' => hash_final($hash)];
}

function op10NativeMain(array $arguments): int
{
    $connected = false;
    try {
        if ($arguments === []) {
            echo json_encode(['status' => 'OP10_NATIVE_PARITY_PREPARED_NO_SQL', 'native_read_authorized' => false]).PHP_EOL;

            return 0;
        }
        if (count($arguments) !== 1 || ! preg_match('/^--receipt=(.+)$/D', $arguments[0], $match)) {
            throw new RuntimeException('NATIVE_RECEIPT_REQUIRED');
        }
        $process = proc_open(['/usr/bin/python3', __DIR__.'/op10_native_recipe.py', '--check-receipt', '--receipt='.$match[1]],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        if (! is_resource($process)) {
            throw new RuntimeException('RECEIPT_CHECK_UNAVAILABLE');
        }
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0 || $err !== '' || json_decode($out, true, flags: JSON_THROW_ON_ERROR) !== ['status' => 'NATIVE_RECEIPT_VERIFIED_NO_SQL']) {
            throw new RuntimeException('NATIVE_RECEIPT_REQUIRED');
        }
        require __DIR__.'/../vendor/autoload.php';
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        if (! $app->isLocal() || ! $app->runningInConsole() || $app->configurationIsCached() || config('catalogue.source') !== 'files') {
            throw new RuntimeException('LOCAL_FILE_SOURCE_REQUIRED');
        }
        $access = storage_path('app/private/database-access/20261007T222611Z-9710d513-f606-49e5-a2a3-0d28926b3307/.env.homya_local_reader');
        $guard = new ArchiveDatabaseGuard;
        $config = $guard->configurationForRole('homya_local_reader', $access, 'reader');
        $connection = DB::connectUsing('homya_op10_parity_reader', $config, true);
        unset($config);
        $connected = true;
        $connection->setReconnector(static fn () => throw new RuntimeException('READ_RECONNECT_FORBIDDEN'));
        $guard->validateConnectedRole($connection, 'reader');
        $profile = CatalogueReadProfile::loadApproved();
        $payload = (new CatalogueArchiveStore($connection, CatalogueReadProfile::LOT))->snapshot();
        if ($payload->digest() !== CatalogueReadProfile::PAYLOAD || $payload->counts() !== $profile->data['counts']
            || ! StrictJson::same(StrictJson::decode(StrictJson::encode(CatalogueReadProfile::fromPayload($payload)->data), false), StrictJson::decode(StrictJson::encode($profile->data), false))) {
            throw new RuntimeException('NATIVE_PAYLOAD_DIVERGENT');
        }
        unset($payload);
        $locales = [];
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $start = hrtime(true);
            $file = new Catalogue(new FileCatalogueSource);
            $sql = new Catalogue(new SqlCatalogueSource(fn () => $connection, $profile));
            $locales[$locale] = op10Compare($file, $sql, 3709) + ['seconds' => (hrtime(true) - $start) / 1e9];
            unset($file, $sql);
        }
        echo json_encode(['status' => 'NATIVE_PARITY_VERIFIED_NOT_ACTIVATED', 'lot_key' => CatalogueReadProfile::LOT,
            'payload_sha256' => CatalogueReadProfile::PAYLOAD, 'locales' => $locales, 'peak_bytes' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'), 'activation' => false], JSON_THROW_ON_ERROR).PHP_EOL;

        return 0;
    } catch (Throwable) {
        echo json_encode(['status' => 'STOP_INSPECT_NO_RETRY', 'details_withheld' => true, 'activation' => false]).PHP_EOL;

        return 1;
    } finally {
        if ($connected) {
            DB::purge('homya_op10_parity_reader');
        }
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(op10NativeMain(array_slice($argv, 1)));
}
