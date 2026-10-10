<?php

use App\Services\ArchiveDatabaseGuard;
use App\Services\CatalogueArchiveImporter;
use App\Services\PrivateLocalFiles;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

function op06_require(bool $value, string $code): void
{
    if (! $value) {
        throw new RuntimeException($code);
    }
}

/** Validation is offline; only the explicitly named inspect/wait modes run SELECT. */
function op06_admin(array $arguments): array
{
    $process = proc_open(['/usr/bin/python3', __DIR__.'/op06_xampp_admin.py', ...$arguments],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, dirname(__DIR__));
    op06_require(is_resource($process), 'ADMIN_PROCESS_FAILED');
    $text = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $code = proc_close($process);
    op06_require($code === 0 && is_string($text), 'ADMIN_GUARD_REFUSED');

    return json_decode($text, true, 64, JSON_THROW_ON_ERROR);
}

function op06_receipt(string $path): void
{
    op06_admin(['--validate-receipt', '--receipt='.$path]);
}

function op06_connect(string $role, string $path): Connection
{
    op06_require(in_array($role, ['import', 'reader'], true), 'TEST_ROLE_REQUIRED');
    $guard = new ArchiveDatabaseGuard;
    $config = $guard->configurationForRole('homya_test_'.$role, $path, $role);
    $connection = DB::connectUsing('op06_'.$role.'_'.getmypid(), $config, true);
    unset($config);
    CatalogueArchiveImporter::preventReplay($connection);
    $guard->validateConnectedRole($connection, $role);
    $connection->statement('SET SESSION innodb_lock_wait_timeout=5');
    $info = $connection->selectOne('SELECT @@session.innodb_lock_wait_timeout AS wait_seconds, @@session.autocommit AS autocommit, @@session.tx_isolation AS isolation, CONNECTION_ID() AS connection_id');
    op06_require((int) $info->wait_seconds === 5 && (int) $info->autocommit === 1, 'RECIPE_SESSION_INVALID');
    op06_require(! (bool) $connection->getPdo()->getAttribute(PDO::ATTR_EMULATE_PREPARES), 'NATIVE_PDO_REQUIRED');

    return $connection;
}

function op06_save(string $directory, string $name, array $data): void
{
    op06_require(preg_match('/^[a-z0-9-]+\.json$/D', $name) === 1, 'REPORT_NAME');
    $f = fopen($directory.'/'.$name, 'x');
    op06_require(is_resource($f), 'REPORT_COLLISION');
    fwrite($f, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    fclose($f);
}

function op06_parent(string $directory, string $receipt, bool $worker = false): void
{
    $root = dirname(__DIR__).'/output/qa-import';
    op06_require(dirname($directory) === $root && realpath($directory) === $directory
        && preg_match('/^op06-run-[a-zA-Z0-9-]+$/D', basename($directory)) === 1, 'RUN_DIRECTORY_REQUIRED');
    PrivateLocalFiles::requireMode($directory, 0700, true);
    $file = $directory.'/'.($worker ? 'driver.json' : 'parent.json');
    op06_require(! is_link($file) && is_file($file), 'PARENT_REQUIRED');
    PrivateLocalFiles::requireMode($file, 0600);
    $parent = json_decode(file_get_contents($file), true, 8, JSON_THROW_ON_ERROR);
    op06_require(function_exists('posix_getppid') && ($parent['pid'] ?? null) === posix_getppid()
        && ($parent['receipt_sha256'] ?? null) === hash_file('sha256', $receipt), 'PARENT_SCOPE_INVALID');
    $lock = fopen($root.'/.op06.lock', 'r');
    op06_require(is_resource($lock), 'PARENT_LOCK_REQUIRED');
    $acquired = flock($lock, LOCK_EX | LOCK_NB);
    if ($acquired) {
        flock($lock, LOCK_UN);
    }
    fclose($lock);
    op06_require(! $acquired, 'PARENT_LOCK_NOT_HELD');
}

function op06_event(array $event): void
{
    echo json_encode($event + ['pid' => getmypid(), 'time_ns' => hrtime(true)], JSON_THROW_ON_ERROR)."\n";
    fflush(STDOUT);
}

function op06_go(): void
{
    $read = [STDIN];
    $write = $except = [];
    op06_require(stream_select($read, $write, $except, 10) === 1 && fgets(STDIN) === "go\n", 'RENDEZVOUS_TIMEOUT');
}

function op06_read_event(array $worker, string $expected, int $seconds = 10): array
{
    stream_set_blocking($worker['pipes'][1], false);
    $deadline = hrtime(true) + $seconds * 1000000000;
    $line = '';
    while (! str_ends_with($line, "\n")) {
        op06_require(hrtime(true) < $deadline, 'WORKER_EVENT_TIMEOUT');
        $read = [$worker['pipes'][1]];
        $write = $except = [];
        if (stream_select($read, $write, $except, 0, 50000) === 1) {
            $part = fgets($read[0], 8192);
            op06_require(is_string($part) && $part !== '', 'WORKER_EVENT_INVALID');
            $line .= $part;
            op06_require(strlen($line) < 8192, 'WORKER_EVENT_INVALID');
        }
    }
    $event = json_decode($line, true, 16, JSON_THROW_ON_ERROR);
    op06_require(($event['event'] ?? null) === $expected && ($event['pid'] ?? null) === $worker['pid'], 'WORKER_EVENT_INVALID');

    return $event;
}

function op06_start_worker(string $mode, array $args): array
{
    $process = proc_open([PHP_BINARY, __DIR__.'/op06_recipe.php', '--worker='.$mode, '--receipt='.$args['receipt'],
        '--import-access='.$args['import-access'], '--run-dir='.$args['run-dir']],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, dirname(__DIR__));
    op06_require(is_resource($process), 'WORKER_START_FAILED');
    $state = proc_get_status($process);
    $worker = ['process' => $process, 'pipes' => $pipes, 'pid' => $state['pid']];
    try {
        $worker['ready'] = op06_read_event($worker, 'ready');
    } catch (Throwable $e) {
        op06_close_worker($worker, true);
        throw $e;
    }

    return $worker;
}

function op06_send_go(array $worker): void
{
    op06_require(fwrite($worker['pipes'][0], "go\n") === 3, 'WORKER_SIGNAL_FAILED');
    fflush($worker['pipes'][0]);
}

function op06_close_worker(array $worker, bool $terminate = false): void
{
    if ($terminate && proc_get_status($worker['process'])['running']) {
        proc_terminate($worker['process']);
    }
    foreach ($worker['pipes'] as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    $code = proc_close($worker['process']);
    if (! $terminate) {
        op06_require($code === 0, 'WORKER_EXIT_FAILED');
    }
}
