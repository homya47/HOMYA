<?php

/** OP-05 journal only. Offline by default; --probe is read-only.
 * --create requires a separately issued private approval receipt, a fresh
 * administrative inventory, and the exact frozen journal/code hashes.
 * No migrations, grants, imports, retries, cleanup or default DB connection.
 */

use App\Services\ArchiveDatabaseGuard;
use App\Services\CatalogueArchiveSchema;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$options = getopt('', ['probe', 'create', 'access:', 'receipt:']);
$connection = null;
$lock = null;
$attempted = false;
$exitCode = 0;

function op05Admin(array $arguments): void
{
    $process = proc_open(['/usr/bin/python3', __DIR__.'/op05_xampp_admin.py', ...$arguments],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
    if (! is_resource($process)) {
        throw new RuntimeException('ADMIN_HELPER_UNAVAILABLE');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    unset($stderr);
    if ($code !== 0 || ! is_string($stdout)) {
        throw new RuntimeException('ADMIN_CHECK_FAILED');
    }
    $result = json_decode($stdout, true, 32, JSON_THROW_ON_ERROR);
    if (! in_array($result['status'] ?? null, ['CHECKS_PASSED', 'RECEIPT_VALIDATED_NO_SQL'], true)) {
        throw new RuntimeException('ADMIN_CHECK_FAILED');
    }
}

try {
    if (! $app->isLocal() || $app->configurationIsCached()
        || array_diff(array_keys($options), ['probe', 'create', 'access', 'receipt'])
        || (isset($options['probe']) && isset($options['create']))) {
        throw new RuntimeException('LOCAL_EXPLICIT_MODE_REQUIRED');
    }
    // getopt does not reject unknown arguments, so check the raw argument names.
    foreach (array_slice($argv, 1) as $argument) {
        if (! preg_match('/^--(probe|create|access|receipt)(=|$)/D', $argument)) {
            throw new RuntimeException('ARGUMENT_INVALID_USE_EQUALS');
        }
    }
    $schema = new CatalogueArchiveSchema;
    $frozen = json_decode(file_get_contents(__DIR__.'/../output/qa-import/op04-complement-schema-final.json'), true, 32, JSON_THROW_ON_ERROR);
    if (hash('sha256', $schema->ddl()) !== $frozen['sha256'] || $schema->fileDigest() !== $frozen['files_sha256']) {
        throw new RuntimeException('SCHEMA_CODE_CHANGED');
    }
    $journal = file_get_contents(__DIR__.'/../output/qa-import/op05-journal-propose.sql');
    if (! is_string($journal) || hash('sha256', $journal) !== '66d40df56dfaf0991746682aa69d4f3e510d6fb435ee7bb7f48557b99f0b5f1d') {
        throw new RuntimeException('JOURNAL_DDL_CHANGED');
    }
    if (! isset($options['probe']) && ! isset($options['create'])) {
        if ($options !== []) {
            throw new RuntimeException('OFFLINE_ACCESS_FORBIDDEN');
        }
        echo json_encode(['status' => 'JOURNAL_PREPARED_NO_SQL', 'journal_sha256' => hash('sha256', $journal),
            'archive_ddl_sha256' => $frozen['sha256'], 'archive_files_sha256' => $frozen['files_sha256']], JSON_THROW_ON_ERROR).PHP_EOL;
        exit(0);
    }
    if (! is_string($options['access'] ?? null) || (! isset($options['create']) && isset($options['receipt']))) {
        throw new RuntimeException('ACCESS_SCOPE_INVALID');
    }
    if (isset($options['create'])) {
        if (! is_string($options['receipt'] ?? null)) {
            throw new RuntimeException('RECEIPT_REQUIRED');
        }
        op05Admin(['--validate-receipt', '--receipt='.$options['receipt']]);
        $path = __DIR__.'/../output/qa-import/.op05.lock';
        if (is_link($path)) {
            throw new RuntimeException('LOCK_INVALID');
        }
        $lock = fopen($path, 'c+');
        if (! is_resource($lock) || ! chmod($path, 0600) || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('OP05_ALREADY_RUNNING');
        }
        $report = dirname(__DIR__).'/output/qa-import/op05-before-journal-'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(4)).'.json';
        op05Admin(['--phase=empty', '--report='.$report]);
    }
    $guard = app(ArchiveDatabaseGuard::class);
    $configuration = $guard->configurationForRole('homya_test_schema', $options['access'], 'schema');
    $connection = DB::connectUsing('homya_op05_journal', $configuration, true);
    unset($configuration);
    $guard->validateConnectedRole($connection, 'schema');
    if ($connection->getSchemaBuilder()->getTables() !== [] || $connection->transactionLevel() !== 0) {
        throw new RuntimeException('EMPTY_TEST_REQUIRED');
    }
    if (isset($options['create'])) {
        op05Admin(['--validate-receipt', '--receipt='.$options['receipt']]);
        $attempted = true;
        $connection->statement($journal);
        $report = dirname(__DIR__).'/output/qa-import/op05-after-journal-'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(4)).'.json';
        op05Admin(['--phase=journal', '--report='.$report]);
    }
    echo json_encode(['status' => $attempted ? 'JOURNAL_CREATED_ADMIN_VERIFIED_NO_MIGRATION' : 'SCHEMA_CONNECTION_READ_ONLY_VERIFIED',
        'database' => 'homya_test', 'account' => 'homya_test_schema@localhost', 'ddl_executed' => $attempted,
        'migration_grant_import_executed' => false], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    // Never emit exception messages: a PDO/framework error may include credentials.
    echo json_encode(['status' => 'STOP_INSPECT_STATE_NO_AUTO_RETRY', 'journal_create_attempted' => $attempted,
        'automatic_cleanup' => false, 'error_class' => get_class($error)], JSON_THROW_ON_ERROR).PHP_EOL;
    $exitCode = 1;
} finally {
    if ($connection !== null) {
        DB::purge('homya_op05_journal');
    }
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
exit($exitCode);
