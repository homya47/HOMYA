<?php

namespace Tests\Support;

use Closure;
use Illuminate\Database\MariaDbConnection;
use RuntimeException;
use Throwable;

/** Exercises the real Query Builder SQL/bindings, but has no PDO/database implementation. */
class ArchiveConnectionDouble extends MariaDbConnection
{
    public array $rows = ['archive_lots' => [], 'archive_categories' => [], 'archive_products' => [], 'archive_translations' => []];

    public array $operations = [];

    public ?string $failInsertTable = null;

    public bool $failCommit = false;

    public ?Closure $beforeFailedTransition = null;

    private array $before = [];

    private ?string $lockedKey = null;

    public function __construct(string $role = 'import')
    {
        $config = config('database.connections.homya_test_'.$role);
        $config['password'] = str_repeat('f', 64); // Synthetic fixture only.
        parent::__construct(fn () => throw new RuntimeException('REAL_PDO_FORBIDDEN'), 'homya_test', '', $config);
    }

    public function select($query, $bindings = [], $useReadPdo = true, array $fetchUsing = [])
    {
        $this->operations[] = ['select', $query, $bindings, $this->transactions];
        if (str_contains($query, 'CURRENT_USER()')) {
            return [(object) ['identity' => $this->getConfig('username').'@localhost', 'database_name' => 'homya_test',
                'version' => config('homya.xampp_version'), 'socket' => config('homya.xampp_socket'), 'datadir' => config('homya.xampp_datadir'),
                'port' => '3307', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin', 'packet_global' => 16777216, 'packet_session' => 16777216,
                'foreign_keys' => 1, 'checks' => 1, 'sql_mode' => 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION']];
        }
        if (! preg_match('/from `(archive_[a-z]+)` where `lot_key` = \?/', $query, $m) || count($bindings) !== 1) {
            throw new RuntimeException('UNEXPECTED_TEST_SELECT');
        }
        if (str_contains($query, 'for update')) {
            if ($this->transactions !== 1 || $m[1] !== 'archive_lots') {
                throw new RuntimeException('INVALID_TEST_LOCK');
            }
            $this->lockedKey = $bindings[0];
        }

        return array_values(array_map(fn ($row) => (object) $row, array_filter($this->rows[$m[1]], fn ($row) => $row['lot_key'] === $bindings[0])));
    }

    public function insert($query, $bindings = [], $sequence = null)
    {
        if (! preg_match('/insert into `(archive_[a-z]+)` \((.*?)\) values \(/', $query, $m)) {
            throw new RuntimeException('UNEXPECTED_TEST_INSERT');
        }
        preg_match_all('/`([^`]+)`/', $m[2], $cols);
        $row = array_combine($cols[1], $bindings);
        if ($m[1] !== 'archive_lots' && ($this->transactions !== 1 || $this->lockedKey !== $row['lot_key'])) {
            throw new RuntimeException('CHILD_WRITE_WITHOUT_LOCK');
        }
        $this->operations[] = ['insert', $m[1], $this->transactions, $this->lockedKey];
        if ($this->failInsertTable === $m[1]) {
            throw new RuntimeException('SYNTHETIC_INSERT_FAILURE');
        }
        $this->rows[$m[1]][] = $row;

        return true;
    }

    public function update($query, $bindings = [])
    {
        if (! preg_match('/update `archive_lots` set (.*?) where `lot_key` = \?/', $query, $m) || $this->transactions !== 1) {
            throw new RuntimeException('UNEXPECTED_TEST_UPDATE');
        }
        preg_match_all('/`([^`]+)` = \?/', $m[1], $cols);
        $key = array_pop($bindings);
        if ($this->lockedKey !== $key || array_diff($cols[1], ['load_state', 'verified_at', 'verification_document'])) {
            throw new RuntimeException('INVALID_TEST_UPDATE');
        }
        $values = array_combine($cols[1], $bindings);
        $this->operations[] = ['update', $values, $this->transactions, $this->lockedKey];
        foreach ($this->rows['archive_lots'] as &$row) {
            if ($row['lot_key'] === $key) {
                $row = array_replace($row, $values);

                return 1;
            }
        }

        return 0;
    }

    public function beginTransaction()
    {
        if ($this->transactions !== 0) {
            throw new RuntimeException('NESTED_TEST_TRANSACTION');
        }
        if ($this->beforeFailedTransition && in_array(['rollback'], $this->operations, true)) {
            ($this->beforeFailedTransition)($this);
            $this->beforeFailedTransition = null;
        }
        $this->before = $this->rows;
        $this->transactions = 1;
        $this->operations[] = ['begin'];
    }

    public function commit()
    {
        $this->operations[] = ['commit'];
        $this->transactions = 0;
        $this->lockedKey = null;
        if ($this->failCommit) {
            throw new RuntimeException('SYNTHETIC_UNCERTAIN_COMMIT');
        }
    }

    public function rollBack($toLevel = null)
    {
        $this->rows = $this->before;
        $this->operations[] = ['rollback'];
        $this->transactions = 0;
        $this->lockedKey = null;
    }

    public function transaction(Closure $callback, $attempts = 1)
    {
        if ($attempts !== 1) {
            throw new RuntimeException('TEST_RETRY_FORBIDDEN');
        }
        $this->beginTransaction();
        try {
            $value = $callback($this);
            $this->commit();

            return $value;
        } catch (Throwable $e) {
            if ($this->transactions === 1) {
                $this->rollBack();
            }
            throw $e;
        }
    }
}
