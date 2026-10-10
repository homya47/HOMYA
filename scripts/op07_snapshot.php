<?php

use App\Services\CatalogueArchivePayload;
use Illuminate\Contracts\Console\Kernel;
use Tests\Support\Op06Recipe;

/** OP-07 CLI-only snapshots. Inclusion defines pure helpers; no bootstrap/connection. */
function op07_assert(bool $condition, string $code): void
{
    if (! $condition) {
        throw new RuntimeException($code);
    }
}

function op07_row_bytes(array $values): string
{
    $encoded = '';
    foreach ($values as $value) {
        op07_assert($value === null || is_string($value), 'ROW_BYTES_REQUIRED');
        $encoded .= $value === null ? 'N;' : 'B'.strlen($value).':'.$value.';';
    }

    return $encoded;
}

function op07_hash_rows(array $rows): array
{
    $hash = hash_init('sha256');
    foreach ($rows as $row) {
        $encoded = op07_row_bytes(array_values($row));
        hash_update($hash, strlen($encoded).':'.$encoded);
    }

    return ['rows' => count($rows), 'sha256' => hash_final($hash)];
}

function op07_payload_rows(array $rows, array $columns): array
{
    foreach ($rows as &$row) {
        foreach ($columns as $column) {
            $key = $column['name'];
            if ($row[$key] !== null && in_array($column['type'], ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'], true)) {
                op07_assert(is_string($row[$key]) && preg_match('/^-?\d+$/D', $row[$key]) === 1, 'INTEGER_BYTES_INVALID');
                $number = filter_var($row[$key], FILTER_VALIDATE_INT);
                op07_assert($number !== false, 'INTEGER_RANGE_INVALID');
                $row[$key] = $number;
            }
        }
    }

    return $rows;
}

function op07_read_snapshot(PDO $pdo, string $database): array
{
    $tables = ['archive_lots', 'archive_categories', 'archive_products', 'archive_translations', 'migrations'];
    $pdo->exec("SET SESSION collation_connection='utf8mb4_bin', sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");
    $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
    try {
        $data = $summary = [];
        $rawBytes = 0;
        foreach ($tables as $table) {
            $q = $pdo->prepare('SELECT COLUMN_NAME AS name, DATA_TYPE AS type, COLUMN_TYPE AS physical_type, IS_NULLABLE AS nullable, COLLATION_NAME AS collation FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
            $q->execute([$database, $table]);
            $columns = $q->fetchAll(PDO::FETCH_ASSOC);
            op07_assert($columns !== [], 'SNAPSHOT_COLUMNS_MISSING');
            $q = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND CONSTRAINT_NAME='PRIMARY' ORDER BY ORDINAL_POSITION");
            $q->execute([$database, $table]);
            $keys = $q->fetchAll(PDO::FETCH_COLUMN);
            op07_assert($keys !== [] && count(array_filter($keys, fn ($x) => preg_match('/^[a-z_]+$/D', $x))) === count($keys), 'SNAPSHOT_PRIMARY_KEY');
            $rows = $pdo->query('SELECT * FROM `'.$table.'` ORDER BY '.implode(',', array_map(fn ($x) => '`'.$x.'`', $keys)))->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                op07_assert(array_keys($row) === array_column($columns, 'name'), 'SNAPSHOT_COLUMN_ORDER');
                foreach ($row as $value) {
                    $rawBytes += $value === null ? 0 : strlen($value);
                }
            }
            $summary[$table] = ['columns' => $columns, 'primary_key' => $keys, ...op07_hash_rows($rows)];
            $data[$table] = op07_payload_rows($rows, $columns);
        }
        $journal = array_map(fn ($i, $t) => ['id' => $i, 'migration' => '2026_10_08_00000'.$i.'_create_'.$t, 'batch' => 1], range(1, 4), array_slice($tables, 0, 4));
        op07_assert($data['migrations'] === $journal, 'SNAPSHOT_JOURNAL_CHANGED');
        $realKey = '48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809';
        $expected = [$realKey => ['state' => 'verified', 'digest' => '49811854d0b4a8da4e3cb7b3e66c99ed2f43ae9f3a71875c29073fa636ea37e1']];
        foreach (['transport', 'concurrency', 'failure', 'partial'] as $scenario) {
            $fixture = Op06Recipe::payload($scenario);
            $expected[$fixture->lotKey()] = ['state' => match ($scenario) {
                'failure' => 'failed', 'partial' => 'prepared', default => 'verified',
            }, 'digest' => $fixture->digest(), 'fixture' => $fixture];
        }
        op07_assert(count($data['archive_lots']) === 5, 'SNAPSHOT_LOT_COUNT');
        $seen = $payloads = [];
        foreach ($data['archive_lots'] as $lot) {
            $key = $lot['lot_key'];
            op07_assert(isset($expected[$key]) && ! isset($seen[$key]) && $lot['load_state'] === $expected[$key]['state'], 'SNAPSHOT_LOT_STATE');
            $seen[$key] = true;
            $children = [];
            foreach (array_slice($tables, 1, 3) as $table) {
                $children[] = array_values(array_filter($data[$table], fn ($row) => $row['lot_key'] === $key));
            }
            if ($lot['load_state'] !== 'verified') {
                op07_assert($children === [[], [], []] && $lot['verification_document'] === null && $lot['verified_at'] === null, 'SNAPSHOT_PARTIAL_CHILDREN');
                $expected[$key]['fixture']->assertLotMatches($lot);

                continue;
            }
            $payload = CatalogueArchivePayload::fromStoredRows($lot, ...$children);
            $payload->assertVerified($lot);
            op07_assert($payload->digest() === $expected[$key]['digest'], 'SNAPSHOT_PAYLOAD_CHANGED');
            $payloads[$key] = ['digest' => $payload->digest(), 'counts' => $payload->counts()];
        }
        foreach ($data['archive_categories'] as $row) {
            op07_assert(isset($seen[$row['lot_key']]), 'SNAPSHOT_ORPHAN_LOT');
        }
        $categories = [];
        foreach ($data['archive_categories'] as $row) {
            $categories[$row['lot_key']][$row['slug']] = true;
        }
        foreach ($data['archive_products'] as $row) {
            op07_assert(isset($seen[$row['lot_key']]) && isset($categories[$row['lot_key']][$row['category_slug']]), 'SNAPSHOT_ORPHAN_CATEGORY');
        }
        foreach ($data['archive_translations'] as $row) {
            op07_assert(isset($seen[$row['lot_key']]), 'SNAPSHOT_ORPHAN_TRANSLATION');
        }
        $pdo->exec('ROLLBACK');
        ksort($payloads);

        return ['tables' => $summary, 'payloads' => $payloads, 'raw_value_bytes' => $rawBytes, 'media_included' => false];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function op07_snapshot_main(array $argv): int
{
    try {
        $args = [];
        foreach (array_slice($argv, 1) as $arg) {
            op07_assert(preg_match('/^--(receipt|role|run-dir)=([^\r\n]+)$/D', $arg, $match) === 1 && ! isset($args[$match[1]]), 'SNAPSHOT_ARGUMENTS');
            $args[$match[1]] = $match[2];
        }
        op07_assert(count($args) === 3 && in_array($args['role'], ['source', 'restored'], true), 'SNAPSHOT_ARGUMENTS');
        $root = dirname(__DIR__);
        $process = proc_open(['/usr/bin/python3', $root.'/scripts/op07_xampp_backup.py', '--check-receipt='.$args['receipt']], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        op07_assert(is_resource($process), 'SNAPSHOT_RECEIPT_PROCESS');
        fclose($pipes[0]);
        $answer = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        op07_assert(proc_close($process) === 0 && $error === '' && trim($answer) === '{"status": "RECEIPT_VALID"}', 'SNAPSHOT_RECEIPT_INVALID');
        $dir = $args['run-dir'];
        op07_assert(realpath($dir) === $dir && preg_match('~^'.preg_quote($root.'/storage/app/private/database-backups/op07/', '~').'[0-9]{8}T[0-9]{6}Z-[a-f0-9-]{36}$~D', $dir) === 1 && (fileperms($dir) & 0777) === 0700 && fileowner($dir) === posix_getuid(), 'SNAPSHOT_RUN_DIR');
        require $root.'/vendor/autoload.php';
        $app = require $root.'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        op07_assert($app->environment('local') && $app->runningInConsole(), 'LOCAL_CLI_REQUIRED');
        $database = $args['role'] === 'source' ? 'homya_test' : 'homya_restore_op07';
        $user = $args['role'] === 'source' ? 'root' : 'homya_op07_restore';
        $password = '';
        if ($args['role'] === 'restored') {
            $path = $dir.'/restore-access.json';
            op07_assert(realpath($path) === $path && ! is_link($path) && (fileperms($path) & 0777) === 0600 && fileowner($path) === posix_getuid() && filesize($path) < 4096, 'SNAPSHOT_ACCESS_FILE');
            $access = json_decode(file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
            op07_assert(array_keys($access) === ['database', 'user', 'password'] && $access['database'] === $database && $access['user'] === $user && preg_match('/^[a-f0-9]{64}$/D', $access['password']) === 1, 'SNAPSHOT_ACCESS_SCOPE');
            $password = $access['password'];
        }
        $pdo = new PDO('mysql:unix_socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock;dbname='.$database.';charset=utf8mb4', $user, $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_STRINGIFY_FETCHES => true, PDO::MYSQL_ATTR_LOCAL_INFILE => false]);
        $server = $pdo->query('SELECT CURRENT_USER() AS identity, DATABASE() AS db, VERSION() AS version, @@socket AS socket, @@datadir AS datadir, @@port AS port, @@global.max_allowed_packet AS packet_global, @@session.max_allowed_packet AS packet_session, @@foreign_key_checks AS fk, @@check_constraint_checks AS checks')->fetch(PDO::FETCH_ASSOC);
        op07_assert($server === ['identity' => $user.'@localhost', 'db' => $database, 'version' => '10.4.28-MariaDB', 'socket' => '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock', 'datadir' => '/Applications/XAMPP/xamppfiles/var/mysql/', 'port' => '3307', 'packet_global' => '16777216', 'packet_session' => '16777216', 'fk' => '1', 'checks' => '1'], 'SNAPSHOT_SERVER_TARGET');
        $snapshot = op07_read_snapshot($pdo, $database);
        $pdo = null;
        $snapshot['peak_php_bytes'] = memory_get_peak_usage(true);
        echo json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";

        return 0;
    } catch (Throwable $error) {
        $code = $error instanceof RuntimeException && preg_match('/^[A-Z0-9_]+$/D', $error->getMessage()) ? $error->getMessage() : 'SNAPSHOT_ERROR_DETAILS_WITHHELD';
        echo json_encode(['status' => 'STOP', 'code' => $code])."\n";

        return 1;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(op07_snapshot_main($argv));
}
