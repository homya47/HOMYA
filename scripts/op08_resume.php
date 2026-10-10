<?php

/** Read-only role probes for the separately authorized OP-08 resumption. */

use App\Services\ArchiveDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

function op08ResumeReceipt(string $receipt): void
{
    $process = proc_open(['/usr/bin/python3', __DIR__.'/op08_resume.py', '--validate-receipt', '--receipt='.$receipt],
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
        throw new RuntimeException('NEW_EXECUTION_RECEIPT_REQUIRED');
    }
}

$connection = null;
$exitCode = 0;
try {
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (! preg_match('/^--(probe|journal|role|access|receipt)(?:=(.+))?$/D', $argument, $match)
            || array_key_exists($match[1], $options)
            || in_array($match[1], ['role', 'access', 'receipt'], true) !== isset($match[2])) {
            throw new RuntimeException('ARGUMENT_INVALID');
        }
        $options[$match[1]] = $match[2] ?? true;
    }
    if ($options === []) {
        echo json_encode(['status' => 'RESUMPTION_PROBES_PREPARED_NO_SQL', 'execution_authorized' => false], JSON_THROW_ON_ERROR).PHP_EOL;
        exit(0);
    }
    $role = $options['role'] ?? 'schema';
    if (! isset($options['probe']) || ! is_string($options['receipt'] ?? null)
        || ! is_string($options['access'] ?? null) || ! in_array($role, ['schema', 'reader', 'import'], true)
        || (isset($options['journal']) && $role !== 'schema')) {
        throw new RuntimeException('EXPLICIT_ROLE_AND_RECEIPT_REQUIRED');
    }
    op08ResumeReceipt($options['receipt']);
    require __DIR__.'/../vendor/autoload.php';
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (! $app->isLocal() || $app->configurationIsCached()) {
        throw new RuntimeException('LOCAL_UNCACHED_REQUIRED');
    }
    $guard = app(ArchiveDatabaseGuard::class);
    $configuration = $guard->configurationForRole('homya_local_'.$role, $options['access'], $role);
    $connection = DB::connectUsing('homya_op08_resume_probe', $configuration, true);
    unset($configuration);
    $guard->validateConnectedRole($connection, $role);
    if ($connection->transactionLevel() !== 0) {
        throw new RuntimeException('TRANSACTION_FORBIDDEN');
    }
    if (isset($options['journal']) && array_column($connection->getSchemaBuilder()->getTables(), 'name') !== ['migrations']) {
        throw new RuntimeException('JOURNAL_ONLY_REQUIRED');
    }
    $tables = $role === 'schema' ? ['migrations'] : ['archive_lots', 'archive_categories', 'archive_products', 'archive_translations'];
    foreach ($tables as $table) {
        if ($connection->table($table)->count() !== ($role === 'schema' && ! isset($options['journal']) ? 4 : 0)) {
            throw new RuntimeException('LOCAL_ROW_COUNT_INVALID');
        }
    }
    echo json_encode(['status' => 'LOCAL_ROLE_READ_PROBED', 'role' => $role,
        'journal_empty' => isset($options['journal']), 'sql_write_executed' => false], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    echo json_encode(['status' => 'STOP_INSPECT_NO_RETRY', 'error_class' => get_class($error),
        'sql_write_executed' => false, 'cleanup' => false], JSON_THROW_ON_ERROR).PHP_EOL;
    $exitCode = 1;
} finally {
    if ($connection !== null) {
        DB::purge('homya_op08_resume_probe');
    }
}
exit($exitCode);
