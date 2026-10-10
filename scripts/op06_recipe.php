<?php

/** Offline preparation by default. Executed only by the authorised locked envelope. */
umask(0077);
require dirname(__DIR__).'/vendor/autoload.php';
require __DIR__.'/op06_common.php';

use App\Services\CatalogueArchiveImporter;
use App\Services\CatalogueArchivePayload;
use App\Services\CatalogueArchiveStore;
use App\Services\CatalogueCapsule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\Op06Recipe;

function op06_concurrency(array $args): array
{
    $a = $b = null;
    $done = false;
    try {
        $a = op06_start_worker('concurrency-a', $args);
        op06_send_go($a);
        $locked = op06_read_event($a, 'locked');
        $b = op06_start_worker('concurrency-b', $args);
        op06_require($a['ready']['connection_id'] !== $b['ready']['connection_id'], 'INDEPENDENT_CONNECTIONS_REQUIRED');
        op06_send_go($b);
        $attempt = op06_read_event($b, 'attempting');
        $wait = op06_admin(['--confirm-wait', '--receipt='.$args['receipt'],
            '--blocking='.(int) $a['ready']['connection_id'], '--blocked='.(int) $b['ready']['connection_id']]);
        op06_send_go($a);
        $resultA = op06_read_event($a, 'completed');
        $resultB = op06_read_event($b, 'completed');
        op06_require($resultA['status'] === 'ARCHIVE_VERIFIED_NOT_ACTIVATED'
            && $resultB['status'] === 'ARCHIVE_ALREADY_VERIFIED_UNCHANGED', 'CONCURRENCY_RESULT_INVALID');
        $done = true;

        return ['a' => $a['ready'], 'b' => $b['ready'], 'locked' => $locked, 'attempt' => $attempt,
            'actual_wait' => $wait, 'result_a' => $resultA, 'result_b' => $resultB];
    } finally {
        foreach ([$a, $b] as $worker) {
            if (is_array($worker)) {
                op06_close_worker($worker, ! $done);
            }
        }
    }
}

function op06_timeout(array $args, Connection $db): array
{
    $p = Op06Recipe::payload('timeout');
    $worker = null;
    $done = false;
    $recipe = new Op06Recipe;
    $result = [];
    try {
        $recipe->rollback($db, function () use ($args, $db, $p, &$worker, &$done, &$result): void {
            $db->table('archive_lots')->insert($p->lotRow('2026-10-08 00:00:00.000000'));
            $worker = op06_start_worker('timeout', $args);
            op06_send_go($worker);
            $result = op06_read_event($worker, 'expected-timeout', 10);
            op06_require($result['code'] === 1205, 'TIMEOUT_RESULT_INVALID');
            $done = true;
        });
    } finally {
        if (is_array($worker)) {
            op06_close_worker($worker, ! $done);
        }
    }

    return $result;
}

try {
    $args = [];
    foreach (array_slice($argv, 1) as $arg) {
        op06_require(preg_match('/^--(execute|prepare|resume|worker|receipt|import-access|reader-access|run-dir)(?:=(.*))?$/D', $arg, $match) === 1
            && ! array_key_exists($match[1], $args), 'ARGUMENT_INVALID');
        $args[$match[1]] = $match[2] ?? true;
    }
    if ($args === [] || $args === ['prepare' => true]) {
        $fixtures = [];
        foreach (Op06Recipe::SCENARIOS as $id) {
            $fixtures[$id] = Op06Recipe::payload($id)->summary();
        }
        echo json_encode(['status' => 'PREPARED_NO_SQL', 'fixtures' => $fixtures, 'mutations' => Op06Recipe::mutations(),
            'probe_document_bytes' => [4194304, 2097152], 'synthetic_persistent_lots' => 4,
            'capsule_deadline_seconds' => 1800, 'sql_executed' => false], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n";
        exit(0);
    }
    $worker = $args['worker'] ?? null;
    $resume = ($args['resume'] ?? false) === true;
    $expected = $worker === null ? ['execute', 'receipt', 'import-access', 'reader-access', 'run-dir'] : ['worker', 'receipt', 'import-access', 'run-dir'];
    if ($resume && $worker === null) {
        $expected[] = 'resume';
    }
    op06_require(count($args) === count($expected) && array_diff(array_keys($args), $expected) === []
        && ($worker !== null || $args['execute'] === true), 'EXECUTION_SCOPE_REQUIRED');
    op06_require($worker === null || in_array($worker, ['concurrency-a', 'concurrency-b', 'timeout'], true), 'WORKER_MODE_INVALID');
    op06_receipt($args['receipt']); // Before bootstrap, credential loading or SQL.
    op06_parent($args['run-dir'], $args['receipt'], $worker !== null);
    if ($worker === null) {
        $parent = json_decode(file_get_contents($args['run-dir'].'/parent.json'), true, 8, JSON_THROW_ON_ERROR);
        op06_require($resume === isset($parent['resume_source']), 'RESUME_PARENT_SCOPE');
        op06_admin($resume
            ? ['--inspect-retained', '--receipt='.$args['receipt'], '--run-dir='.$parent['resume_source']]
            : ['--inspect=9', '--receipt='.$args['receipt']]);
        op06_save($args['run-dir'], 'driver.json', ['pid' => getmypid(), 'receipt_sha256' => hash_file('sha256', $args['receipt'])]);
    }
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    op06_require($app->isLocal() && ! $app->configurationIsCached(), 'LOCAL_UNCACHED_REQUIRED');
    $import = op06_connect('import', $args['import-access']);
    if ($worker !== null) {
        $id = (int) $import->selectOne('SELECT CONNECTION_ID() AS id')->id;
        op06_event(['event' => 'ready', 'connection_id' => $id]);
        op06_go();
        $checks = 0;
        $p = Op06Recipe::instrumentedPayload($worker === 'timeout' ? 'timeout' : 'concurrency', function () use (&$checks, $worker): void {
            if (++$checks === 2 && $worker === 'concurrency-a') {
                op06_event(['event' => 'locked']);
                op06_go();
            }
        });
        if ($worker === 'timeout') {
            try {
                // Exercise the same initial INSERT via native PDO; capture expected numeric error.
                $import->table('archive_lots')->insert($p->lotRow('2026-10-08 00:00:00.000000'));
                throw new RuntimeException('TIMEOUT_NOT_OBSERVED');
            } catch (QueryException $e) {
                op06_require((int) ($e->errorInfo[1] ?? 0) === 1205, 'TIMEOUT_UNEXPECTED_CODE');
                op06_event(['event' => 'expected-timeout', 'code' => 1205]);
            }
        } else {
            if ($worker === 'concurrency-b') {
                op06_event(['event' => 'attempting']);
            }
            $result = (new CatalogueArchiveImporter)->load($import, $p);
            op06_event(['event' => 'completed', 'status' => $result['status']]);
        }
        exit(0);
    }
    $reader = op06_connect('reader', $args['reader-access']);
    $sessions = [];
    foreach (['import' => $import, 'reader' => $reader] as $role => $db) {
        $sessions[$role] = (array) $db->selectOne('SELECT CONNECTION_ID() AS connection_id, @@session.tx_isolation AS isolation, @@session.autocommit AS autocommit, @@session.innodb_lock_wait_timeout AS wait_seconds, @@session.max_allowed_packet AS packet_bytes');
        $sessions[$role]['driver'] = $db->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sessions[$role]['client_version'] = $db->getPdo()->getAttribute(PDO::ATTR_CLIENT_VERSION);
        $sessions[$role]['emulate_prepares'] = $db->getPdo()->getAttribute(PDO::ATTR_EMULATE_PREPARES);
    }
    op06_save($args['run-dir'], 'sessions.json', $sessions);
    $recipe = new Op06Recipe;
    if ($resume) {
        $retained = Op06Recipe::payload('transport');
        $snapshot = (new CatalogueArchiveStore($reader, $retained->lotKey()))->snapshot();
        op06_require($snapshot->digest() === $retained->digest() && $snapshot->counts() === $retained->counts(), 'RETAINED_TRANSPORT_CHANGED');
        op06_save($args['run-dir'], 'retained-transport.json', ['status' => 'VERIFIED_READ_ONLY', 'payload_sha256' => $snapshot->digest()]);
    }
    $steps = $resume ? [] : ['integrity', 'collision', 'transport'];
    foreach ($steps as $step) {
        op06_receipt($args['receipt']);
        $recipe->$step($import);
        op06_save($args['run-dir'], $step.'.json', ['status' => 'STEP_COMPLETED', 'evidence' => $recipe->evidence]);
    }
    $recipe->permissions($import, $reader);
    $recipe->failureAndPartial($import, $reader);
    op06_save($args['run-dir'], 'permissions-failure.json', ['status' => 'STEP_COMPLETED', 'evidence' => $recipe->evidence]);
    op06_receipt($args['receipt']);
    op06_save($args['run-dir'], 'timeout.json', op06_timeout($args, $import));
    op06_save($args['run-dir'], 'concurrency.json', op06_concurrency($args));
    $p = Op06Recipe::payload('concurrency');
    op06_require((new CatalogueArchiveStore($reader, $p->lotKey()))->snapshot()->digest() === $p->digest(), 'CONCURRENT_PAYLOAD_CHANGED');
    op06_receipt($args['receipt']);
    $key = '48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809';
    $payload = CatalogueArchivePayload::fromCapsule(new CatalogueCapsule, $key);
    $simulation = json_decode(file_get_contents(dirname(__DIR__).'/output/qa-import/op04-complement-simulation.json'), true, 64, JSON_THROW_ON_ERROR);
    op06_require($payload->digest() === $simulation['payload_sha256'] && $payload->counts() === $simulation['counts'], 'CAPSULE_SIMULATION_CHANGED');
    $payload->verifyFiles();
    $start = hrtime(true);
    $result = (new CatalogueArchiveImporter)->load($import, $payload);
    op06_require($result['status'] === 'ARCHIVE_VERIFIED_NOT_ACTIVATED', 'CAPSULE_IMPORT_FAILED');
    $actual = (new CatalogueArchiveStore($reader, $key))->snapshot();
    op06_require($actual->digest() === $payload->digest() && $actual->counts() === $payload->counts(), 'CAPSULE_READBACK_CHANGED');
    $payload->verifyFiles();
    op06_save($args['run-dir'], 'recipe-result.json', ['status' => 'RECIPE_COMPLETED_NOT_ACTIVATED', 'lot_key' => $key,
        'payload_sha256' => $actual->digest(), 'counts' => $actual->counts(), 'duration_ns' => hrtime(true) - $start,
        'peak_php_bytes' => memory_get_peak_usage(true), 'synthetic_lots_retained' => 4, 'activated' => false]);
    echo json_encode(['status' => 'RECIPE_COMPLETED_NOT_ACTIVATED'])."\n";
} catch (Throwable $e) {
    $code = $e instanceof RuntimeException && preg_match('/^[A-Z0-9_]+$/D', $e->getMessage()) ? $e->getMessage() : 'RECIPE_ERROR_DETAILS_WITHHELD';
    if (isset($args['run-dir']) && is_dir($args['run-dir']) && realpath($args['run-dir']) === $args['run-dir']
        && dirname($args['run-dir']) === dirname(__DIR__).'/output/qa-import') {
        try {
            op06_save($args['run-dir'], 'failure-'.getmypid().'.json', ['status' => 'STOP_STATUS_TO_INSPECT', 'code' => $code]);
        } catch (Throwable) {
            // Preserve the original failure. No retry or cleanup.
        }
    }
    echo json_encode(['status' => 'STOP_STATUS_TO_INSPECT', 'code' => $code])."\n";
    exit(1);
} finally {
    if (isset($app)) {
        foreach (['import', 'reader'] as $role) {
            DB::purge('op06_'.$role.'_'.getmypid());
        }
    }
}
