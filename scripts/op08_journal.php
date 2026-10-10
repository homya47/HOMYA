<?php

/** OP-08 only. No bootstrap, private access or PDO before a valid execution receipt. */

use App\Services\ArchiveDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

function op08Receipt(string $receipt): void
{
    $process = proc_open(['/usr/bin/python3', __DIR__.'/op08_xampp_admin.py', '--validate-receipt', '--receipt='.$receipt],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
    if (! is_resource($process)) {
        throw new RuntimeException('RECEIPT_HELPER_UNAVAILABLE');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    if ($code !== 0 || json_decode($stdout, true, 32, JSON_THROW_ON_ERROR) !== ['status' => 'RECEIPT_VALID_NO_SQL']) {
        throw new RuntimeException('EXECUTION_RECEIPT_REQUIRED');
    }
}

$connection = null;
$attempted = false;
$exitCode = 0;
try {
    // Parse strictly: getopt alone silently ignores unknown/duplicate arguments.
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (! preg_match('/^--(probe|empty|create-journal|role|access|receipt)(?:=(.+))?$/D', $argument, $match)
            || array_key_exists($match[1], $options)) {
            throw new RuntimeException('ARGUMENT_INVALID');
        }
        $valueOption = in_array($match[1], ['role', 'access', 'receipt'], true);
        if ($valueOption !== isset($match[2])) {
            throw new RuntimeException('ARGUMENT_VALUE_INVALID');
        }
        $options[$match[1]] = $match[2] ?? true;
    }
    if ($options === []) {
        echo json_encode(['status' => 'LOCAL_JOURNAL_PREPARED_NO_SQL', 'execution_authorized' => false], JSON_THROW_ON_ERROR).PHP_EOL;
        exit(0);
    }
    if (isset($options['probe']) === isset($options['create-journal'])
        || ! is_string($options['receipt'] ?? null) || ! is_string($options['access'] ?? null)) {
        throw new RuntimeException('EXPLICIT_MODE_AND_RECEIPT_REQUIRED');
    }
    $role = $options['role'] ?? 'schema';
    if (! in_array($role, ['schema', 'reader', 'import'], true)
        || (isset($options['empty']) && (! isset($options['probe']) || $role !== 'schema'))
        || (isset($options['create-journal']) && ($role !== 'schema' || isset($options['empty'])))) {
        throw new RuntimeException('ROLE_SCOPE_INVALID');
    }
    op08Receipt($options['receipt']); // Before even loading Laravel or access content.
    require __DIR__.'/../vendor/autoload.php';
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (! $app->isLocal() || $app->configurationIsCached()) {
        throw new RuntimeException('LOCAL_UNCACHED_REQUIRED');
    }
    $guard = app(ArchiveDatabaseGuard::class);
    $configuration = $guard->configurationForRole('homya_local_'.$role, $options['access'], $role);
    $connection = DB::connectUsing('homya_op08_journal', $configuration, true);
    unset($configuration);
    $guard->validateConnectedRole($connection, $role);
    if ($connection->transactionLevel() !== 0) {
        throw new RuntimeException('TRANSACTION_FORBIDDEN');
    }
    if (isset($options['empty']) || isset($options['create-journal'])) {
        if ($connection->getSchemaBuilder()->getTables() !== []) {
            throw new RuntimeException('EMPTY_LOCAL_REQUIRED');
        }
        if (isset($options['create-journal'])) {
            $journal = file_get_contents(__DIR__.'/../output/qa-import/op05-journal-propose.sql');
            if (hash('sha256', $journal) !== '66d40df56dfaf0991746682aa69d4f3e510d6fb435ee7bb7f48557b99f0b5f1d') {
                throw new RuntimeException('JOURNAL_CHANGED');
            }
            op08Receipt($options['receipt']);
            $attempted = true;
            $connection->statement($journal);
        }
        $status = $attempted ? 'LOCAL_JOURNAL_CREATED' : 'LOCAL_SCHEMA_EMPTY_PROBED';
    } else {
        $tables = $role === 'schema' ? ['migrations'] : ['archive_lots', 'archive_categories', 'archive_products', 'archive_translations'];
        foreach ($tables as $table) {
            if ($connection->table($table)->count() !== ($role === 'schema' ? 4 : 0)) {
                throw new RuntimeException('LOCAL_ROW_COUNT_INVALID');
            }
        }
        $status = 'LOCAL_ROLE_READ_PROBED';
    }
    echo json_encode(['status' => $status, 'role' => $role, 'journal_create_attempted' => $attempted,
        'import_executed' => false, 'activated' => false], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    // PDO/framework diagnostics may contain credentials: never print the message.
    echo json_encode(['status' => 'STOP_INSPECT_NO_RETRY', 'error_class' => get_class($error),
        'journal_create_attempted' => $attempted, 'cleanup' => false], JSON_THROW_ON_ERROR).PHP_EOL;
    $exitCode = 1;
} finally {
    if ($connection !== null) {
        DB::purge('homya_op08_journal');
    }
}
exit($exitCode);
