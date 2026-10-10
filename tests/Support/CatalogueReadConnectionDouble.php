<?php

namespace Tests\Support;

use Closure;
use Illuminate\Database\MariaDbConnection;
use RuntimeException;

/** Actual Query Builder, synthetic rows only. Every native PDO/write call is forbidden. */
final class CatalogueReadConnectionDouble extends MariaDbConnection
{
    public array $operations = [];

    public function __construct(public array $rows, private ?Closure $loadRows = null)
    {
        parent::__construct(fn () => throw new RuntimeException('REAL_PDO_FORBIDDEN'), 'offline_only');
    }

    public function select($query, $bindings = [], $useReadPdo = true, array $fetchUsing = [])
    {
        if (! preg_match('/^select (.*?) from `(archive_[a-z]+)` where `lot_key` = \?/', $query, $match)
            || str_contains($query, 'for update') || ! isset($this->rows[$match[2]]) || ! isset($bindings[0])) {
            throw new RuntimeException('OFFLINE_READ_QUERY_UNEXPECTED');
        }
        $table = $match[2];
        $input = $this->loadRows === null ? $this->rows[$table] : ($this->loadRows)($table, $query, $bindings);
        $rows = array_values(array_filter($input, fn ($r) => $r['lot_key'] === $bindings[0]));
        if (str_contains($query, '`locale` = ?')) {
            if (count($bindings) !== 2) {
                throw new RuntimeException('OFFLINE_BINDINGS_INVALID');
            }
            $rows = array_values(array_filter($rows, fn ($r) => $r['locale'] === $bindings[1]));
        } elseif (str_contains($query, '`slug` in (')) {
            $slugs = array_slice($bindings, 1);
            $rows = array_values(array_filter($rows, fn ($r) => in_array($r['slug'], $slugs, true)));
        } elseif (count($bindings) !== 1) {
            throw new RuntimeException('OFFLINE_BINDINGS_INVALID');
        }
        if (preg_match('/order by `([a-z_]+)` asc/', $query, $sort)) {
            usort($rows, fn ($a, $b) => $a[$sort[1]] <=> $b[$sort[1]]);
        }
        if (str_contains($query, 'limit 1')) {
            $rows = array_slice($rows, 0, 1);
        }
        preg_match_all('/`([a-z_]+)`/', $match[1], $columns);
        $this->operations[] = ['table' => $table, 'query' => $query, 'row_count' => count($rows)];

        return array_map(function ($row) use ($match, $columns) {
            $result = $match[1] === '*' ? $row : array_intersect_key($row, array_flip($columns[1]));
            if (str_contains($match[1], 'SHA2(manifest_document, 256) AS manifest_sha256')) {
                $result['manifest_sha256'] = hash('sha256', $row['manifest_document']);
            }

            return (object) $result;
        }, $rows);
    }

    public function statement($query, $bindings = [])
    {
        throw new RuntimeException('OFFLINE_WRITE_FORBIDDEN');
    }

    public function affectingStatement($query, $bindings = [])
    {
        throw new RuntimeException('OFFLINE_WRITE_FORBIDDEN');
    }

    public function unprepared($query)
    {
        throw new RuntimeException('OFFLINE_WRITE_FORBIDDEN');
    }

    public function beginTransaction()
    {
        throw new RuntimeException('OFFLINE_TRANSACTION_FORBIDDEN');
    }
}
