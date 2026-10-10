<?php

/** OP-09 only: no bootstrap, credentials or PDO at inclusion/default/refusal. */

use App\Services\ArchiveDatabaseGuard;
use App\Services\CatalogueArchiveImporter;
use App\Services\CatalogueArchivePayload;
use App\Services\CatalogueArchiveStore;
use App\Services\CatalogueCapsule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

function op09Reconcile(Connection $connection, CatalogueArchivePayload $expected, bool $write): array
{
    if ($connection->getConfig('database') !== 'homya_local'
        || $connection->getConfig('username') !== ($write ? 'homya_local_import' : 'homya_local_reader')) {
        throw new RuntimeException('LOCAL_ROLE_REQUIRED');
    }
    $counts = $expected->counts();
    if ($write) {
        app(ArchiveDatabaseGuard::class)->validateConnectedRole($connection, 'import');
        // Refuse even an already verified lot: this operation starts from empty local archives.
        foreach (['archive_lots', 'archive_categories', 'archive_products', 'archive_translations'] as $table) {
            if ($connection->table($table)->count() !== 0) {
                throw new RuntimeException('EMPTY_LOCAL_ARCHIVE_REQUIRED');
            }
        }
        $result = (new CatalogueArchiveImporter)->load($connection, $expected);
        if (($result['status'] ?? null) !== 'ARCHIVE_VERIFIED_NOT_ACTIVATED'
            || ($result['counts'] ?? null) !== $counts || ($result['activated'] ?? null) !== false) {
            throw new RuntimeException('IMPORT_RESULT_INVALID');
        }
        $status = 'ARCHIVE_VERIFIED_NOT_ACTIVATED';
    } else {
        $actual = (new CatalogueArchiveStore($connection, $expected->lotKey()))->snapshot();
        if ($actual->digest() !== $expected->digest() || $actual->counts() !== $counts) {
            throw new RuntimeException('READ_RECONCILIATION_FAILED');
        }
        $expected->verifyFiles();
        $status = 'ARCHIVE_READ_VERIFIED_NOT_ACTIVATED';
    }

    return ['status' => $status, 'lot_key' => $expected->lotKey(),
        'payload_sha256' => $expected->digest(), 'counts' => $counts, 'activated' => false];
}

function op09Main(array $arguments): int
{
    $connection = null;
    $attempted = false;
    try {
        $options = [];
        foreach ($arguments as $argument) {
            if (! preg_match('/^--(import|read|receipt|run-dir|access)(?:=(.+))?$/D', $argument, $match)
                || array_key_exists($match[1], $options)
                || (in_array($match[1], ['receipt', 'run-dir', 'access'], true) !== isset($match[2]))) {
                throw new RuntimeException('ARGUMENT_INVALID');
            }
            $options[$match[1]] = $match[2] ?? true;
        }
        if ($options === []) {
            echo json_encode(['status' => 'OP09_HELPER_PREPARED_NO_SQL', 'execution_authorized' => false], JSON_THROW_ON_ERROR).PHP_EOL;

            return 0;
        }
        if (isset($options['import']) === isset($options['read'])
            || ! isset($options['receipt'], $options['run-dir'], $options['access'])
            || ! function_exists('posix_getppid')) {
            throw new RuntimeException('EXPLICIT_MODE_RECEIPT_AND_TICKET_REQUIRED');
        }
        $mode = isset($options['import']) ? 'import' : 'read';
        $process = proc_open(['/usr/bin/python3', __DIR__.'/op09_xampp_import.py', '--validate-helper',
            '--receipt='.$options['receipt'], '--run-dir='.$options['run-dir'], '--mode='.$mode,
            '--wrapper-pid='.posix_getppid()], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        if (! is_resource($process)) {
            throw new RuntimeException('RECEIPT_HELPER_UNAVAILABLE');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0 || $stderr !== ''
            || json_decode($stdout, true, 32, JSON_THROW_ON_ERROR) !== ['status' => 'HELPER_TICKET_CONSUMED_NO_SQL']) {
            throw new RuntimeException('EXECUTION_RECEIPT_REQUIRED');
        }
        // The execution receipt and single-use helper ticket have been checked before autoload.
        require __DIR__.'/../vendor/autoload.php';
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        if (! $app->isLocal() || ! $app->runningInConsole() || $app->configurationIsCached()) {
            throw new RuntimeException('LOCAL_UNCACHED_CLI_REQUIRED');
        }
        $key = '48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809';
        $expected = CatalogueArchivePayload::fromCapsule(new CatalogueCapsule, $key);
        if ($expected->digest() !== '49811854d0b4a8da4e3cb7b3e66c99ed2f43ae9f3a71875c29073fa636ea37e1') {
            throw new RuntimeException('PAYLOAD_CHANGED');
        }
        $expected->verifyFiles();
        $role = $mode === 'import' ? 'import' : 'reader';
        $configuration = app(ArchiveDatabaseGuard::class)->configurationForRole('homya_local_'.$role, $options['access'], $role);
        $connection = DB::connectUsing('homya_op09_catalogue', $configuration, true);
        unset($configuration);
        CatalogueArchiveImporter::preventReplay($connection);
        app(ArchiveDatabaseGuard::class)->validateConnectedRole($connection, $role);
        // Session-only bound. No global configuration or service change.
        $connection->statement('SET SESSION innodb_lock_wait_timeout=5');
        $attempted = $mode === 'import';
        echo json_encode(op09Reconcile($connection, $expected, $attempted), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;

        return 0;
    } catch (Throwable $error) {
        // Raw PDO/framework diagnostics can contain credentials; never print them.
        echo json_encode(['status' => 'STOP_INSPECT_NO_RETRY', 'error_class' => get_class($error),
            'import_attempted' => $attempted, 'cleanup' => false, 'activated' => false], JSON_THROW_ON_ERROR).PHP_EOL;

        return 1;
    } finally {
        if ($connection !== null) {
            DB::purge('homya_op09_catalogue');
        }
    }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(op09Main(array_slice($argv, 1)));
}
