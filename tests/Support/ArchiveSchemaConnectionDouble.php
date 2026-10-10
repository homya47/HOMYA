<?php

namespace Tests\Support;

use RuntimeException;

/** Real Laravel grammar/builder/migrator with memory storage and no PDO. */
class ArchiveSchemaConnectionDouble extends ArchiveConnectionDouble
{
    public array $tables = ['migrations'];

    public array $journal = [];

    public array $ddl = [];

    public ?string $failCreate = null;

    public function __construct()
    {
        parent::__construct('schema');
        $this->config['name'] = 'homya_archive_schema_execution';
    }

    public function select($query, $bindings = [], $useReadPdo = true, array $fetchUsing = [])
    {
        if (str_contains($query, 'CURRENT_USER()')) {
            return parent::select($query, $bindings, $useReadPdo, $fetchUsing);
        }
        if (str_contains($query, 'information_schema.tables')) {
            $tables = $this->tables;
            sort($tables);

            return array_map(fn ($name) => (object) ['name' => $name, 'schema' => 'homya_test'], $tables);
        }
        if (str_contains($query, 'from `migrations`') && $bindings === []) {
            if (str_contains($query, 'count(*)')) {
                return [(object) ['aggregate' => count($this->journal)]];
            }
            if (str_contains($query, 'max(`batch`)')) {
                return [(object) ['aggregate' => $this->journal === [] ? null : max(array_column($this->journal, 'batch'))]];
            }
            if (str_contains($query, 'select `migration`')) {
                return array_map(fn ($row) => (object) ['migration' => $row['migration']], $this->journal);
            }
        }
        throw new RuntimeException('UNEXPECTED_SCHEMA_TEST_SELECT');
    }

    public function statement($query, $bindings = [])
    {
        if ($bindings !== [] || ! preg_match('/^(create|alter) table `(archive_[a-z]+)`/', $query, $match)) {
            throw new RuntimeException('UNEXPECTED_SCHEMA_TEST_DDL');
        }
        if ($this->failCreate === $match[2]) {
            throw new RuntimeException('SYNTHETIC_DDL_FAILURE');
        }
        if ($match[1] === 'create') {
            $this->tables[] = $match[2];
        }
        $this->ddl[] = $query;

        return true;
    }

    public function insert($query, $bindings = [], $sequence = null)
    {
        if ($query !== 'insert into `migrations` (`migration`, `batch`) values (?, ?)' || count($bindings) !== 2) {
            throw new RuntimeException('UNEXPECTED_SCHEMA_TEST_INSERT');
        }
        $this->journal[] = ['migration' => $bindings[0], 'batch' => $bindings[1]];

        return true;
    }
}
