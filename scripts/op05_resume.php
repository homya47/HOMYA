<?php

/** Proposed recovery: offline DDL by default. Execute only through the Python
 * envelope, which holds the common lock and checks/grants the two parent rights.
 */

use App\Services\ArchiveDatabaseGuard;
use App\Services\CatalogueArchiveSchema;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$options = getopt('', ['ddl', 'execute', 'receipt:', 'access:']);
$connection = null;
$attempted = false;
$exitCode = 0;

function resumeCheck(array $arguments): void
{
    $process = proc_open(['/usr/bin/python3', __DIR__.'/op05_resume.py', ...$arguments],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
    if (! is_resource($process)) {
        throw new RuntimeException('RECOVERY_HELPER_UNAVAILABLE');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('RECOVERY_CHECK_FAILED');
    }
    $parsed = json_decode($stdout, true, 32, JSON_THROW_ON_ERROR);
    if (! in_array($parsed['status'] ?? null, ['RECEIPT_VALIDATED_NO_SQL', 'CHECKS_PASSED_NEEDS_PHYSICAL_DDL_REVIEW'], true)) {
        throw new RuntimeException('RECOVERY_CHECK_FAILED');
    }
}

try {
    if (! $app->isLocal() || $app->configurationIsCached()) {
        throw new RuntimeException('LOCAL_UNCACHED_REQUIRED');
    }
    foreach (array_slice($argv, 1) as $argument) {
        if (! preg_match('/^--(ddl|execute|receipt|access)(=|$)/D', $argument)) {
            throw new RuntimeException('ARGUMENT_INVALID_USE_EQUALS');
        }
    }
    $schema = new CatalogueArchiveSchema;
    $frozen = json_decode(file_get_contents(__DIR__.'/../output/qa-import/op04-complement-schema-final.json'), true, 32, JSON_THROW_ON_ERROR);
    if (hash('sha256', $schema->ddl()) !== $frozen['sha256'] || $schema->fileDigest() !== $frozen['files_sha256']) {
        throw new RuntimeException('SCHEMA_CHANGED');
    }
    $statements = $schema->statements();
    $remaining = [...array_slice($statements['archive_categories'], 1), ...$statements['archive_products'], ...$statements['archive_translations']];
    $ddl = implode(";\n", $remaining).";\n";
    if (! isset($options['execute'])) {
        if (array_diff(array_keys($options), ['ddl'])) {
            throw new RuntimeException('OFFLINE_ACCESS_FORBIDDEN');
        }
        echo isset($options['ddl']) ? $ddl : json_encode(['status' => 'RECOVERY_PREPARED_NO_SQL', 'remaining_statements' => count($remaining), 'ddl_sha256' => hash('sha256', $ddl)], JSON_THROW_ON_ERROR).PHP_EOL;
        exit(0);
    }
    if (isset($options['ddl']) || ! is_string($options['receipt'] ?? null) || ! is_string($options['access'] ?? null)
        || ! stream_isatty(STDIN)) {
        throw new RuntimeException('RECOVERY_SCOPE_REQUIRED');
    }
    resumeCheck(['--validate-receipt', '--receipt='.$options['receipt']]);
    $report = dirname(__DIR__).'/output/qa-import/op05-resume-before-ddl-'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(4)).'.json';
    resumeCheck(['--inspect=2', '--report='.$report]);
    fwrite(STDOUT, 'Autorisation distincte obtenue pour les deux REFERENCES et cette reprise sans suppression ? (yes/no) [no]: ');
    if (trim(fgets(STDIN)) !== 'yes') {
        throw new RuntimeException('RECOVERY_CONFIRMATION_REQUIRED');
    }
    $guard = app(ArchiveDatabaseGuard::class);
    $config = $guard->configurationForRole('homya_test_schema', $options['access'], 'schema');
    $connection = DB::connectUsing('homya_op05_recovery', $config, true);
    unset($config);
    $guard->validateConnectedRole($connection, 'schema');
    $tables = array_column($connection->getSchemaBuilder()->getTables(), 'name');
    sort($tables);
    $expected = ['archive_categories', 'archive_lots', 'migrations'];
    $journal = $connection->table('migrations')->orderBy('id')->get(['id', 'migration', 'batch']);
    if ($tables !== $expected || $connection->transactionLevel() !== 0 || $journal->count() !== 1
        || $journal[0]->id != 1 || $journal[0]->migration !== substr(CatalogueArchiveSchema::MIGRATIONS[0], 0, -4) || $journal[0]->batch != 1) {
        throw new RuntimeException('PARTIAL_STATE_CHANGED');
    }
    resumeCheck(['--validate-receipt', '--receipt='.$options['receipt']]);
    $attempted = true;
    foreach (array_slice($statements['archive_categories'], 1) as $statement) {
        $connection->statement($statement);
    }
    $connection->table('migrations')->insert(['migration' => substr(CatalogueArchiveSchema::MIGRATIONS[1], 0, -4), 'batch' => 1]);
    $execution = CatalogueArchiveSchema::forExecution($connection, $frozen['sha256']);
    foreach (['archive_products' => 2, 'archive_translations' => 3] as $table => $index) {
        if ($schema->fileDigest() !== $frozen['files_sha256']) {
            throw new RuntimeException('SCHEMA_CHANGED');
        }
        $execution->apply($table);
        $connection->table('migrations')->insert(['migration' => substr(CatalogueArchiveSchema::MIGRATIONS[$index], 0, -4), 'batch' => 1]);
    }
    echo json_encode(['status' => 'RECOVERY_DDL_COMPLETED_NEEDS_ADMIN_REVIEW', 'import_executed' => false], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    $previous = $error instanceof QueryException ? $error->getPrevious() : $error;
    $sqlCode = $previous instanceof PDOException ? ($previous->errorInfo[1] ?? null) : null;
    echo json_encode(['status' => 'STOP_NO_RETRY_OR_CLEANUP', 'ddl_attempted' => $attempted,
        'error_class' => get_class($error), 'sql_error_code' => $sqlCode], JSON_THROW_ON_ERROR).PHP_EOL;
    $exitCode = 1;
} finally {
    if ($connection !== null) {
        DB::purge('homya_op05_recovery');
    }
}
exit($exitCode);
