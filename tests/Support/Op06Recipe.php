<?php

namespace Tests\Support;

use App\Services\CatalogueArchiveImporter;
use App\Services\CatalogueArchivePayload;
use App\Services\CatalogueArchiveStore;
use App\Services\StrictJson;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use RuntimeException;

/** Isolated CLI recipe, never bound to HTTP. No constructor/default connection. */
final class Op06Recipe
{
    public const SCENARIOS = ['integrity', 'parent-a', 'parent-b', 'transport', 'failure', 'timeout', 'concurrency', 'partial', 'hash-collision'];

    public array $evidence = [];

    public static function payload(string $id, ?Closure $verifier = null): CatalogueArchivePayload
    {
        self::ensure(in_array($id, self::SCENARIOS, true), 'FIXTURE_NOT_ALLOWLISTED');

        return CatalogueArchiveFixture::payload(function (array $docs) use ($id): void {
            $docs['storage/app/catalogue/index.json']->meta->op06_recipe = 'OP06-v01-'.$id;
        }, $verifier);
    }

    public static function ensure(bool $value, string $code): void
    {
        if (! $value) {
            throw new RuntimeException($code);
        }
    }

    /** Synthetic fixture evidence only; a hook cannot replace the verifier contract. */
    public static function instrumentedPayload(string $id, Closure $hook): CatalogueArchivePayload
    {
        $key = self::payload($id)->lotKey();

        return self::payload($id, function () use ($key, $hook): array {
            $hook();

            return ['lot_key' => $key, 'sql_executed' => false, 'synthetic' => true];
        });
    }

    public function counts(Connection $db): array
    {
        return array_map(fn ($t) => $db->table($t)->count(), ['archive_lots', 'archive_categories', 'archive_products', 'archive_translations']);
    }

    public function rollback(Connection $db, Closure $callback): void
    {
        self::ensure($db->transactionLevel() === 0, 'RECIPE_TRANSACTION_REQUIRED');
        $before = $this->counts($db);
        $db->beginTransaction();
        try {
            $callback();
        } finally {
            self::ensure($db->transactionLevel() === 1, 'RECIPE_TRANSACTION_CHANGED');
            $db->rollBack();
            self::ensure($before === $this->counts($db), 'ROLLBACK_COUNTS_CHANGED');
        }
    }

    private function rejected(Connection $db, string $id, Closure $callback, array $codes): void
    {
        $before = $this->counts($db);
        $db->statement('SAVEPOINT op06_case');
        try {
            $callback();
            throw new RuntimeException('EXPECTED_REJECTION_MISSING');
        } catch (QueryException $e) {
            $code = (int) ($e->errorInfo[1] ?? 0);
            self::ensure(in_array($code, $codes, true), 'UNEXPECTED_SQL_CODE');
            $this->evidence[] = ['case' => $id, 'code' => $code, 'state' => (string) ($e->errorInfo[0] ?? ''), 'expected_refusal' => true];
        } finally {
            $db->statement('ROLLBACK TO SAVEPOINT op06_case');
            $db->statement('RELEASE SAVEPOINT op06_case');
        }
        self::ensure($before === $this->counts($db), 'REJECTED_STATEMENT_MUTATED_ROWS');
    }

    public static function mutations(): array
    {
        return [
            'archive_lots' => ['lot_key' => [str_repeat('a', 63), str_repeat('a', 65), str_repeat('A', 64), str_repeat('g', 64)],
                'format_version' => [0, 2], 'manifest_document' => ['{'], 'archive_document' => ['{'],
                'verification_document' => ['{', '{}'], 'verified_at' => ['2026-10-08 00:00:00.000000'],
                'load_state' => ['unknown', 'verified'], 'registered_at' => [null, '2026-02-30 00:00:00.000000']],
            'archive_categories' => ['slug' => ['', str_repeat('a', 222), 'has_underscore', 'Uppercase'], 'source_name' => [null]],
            'archive_products' => ['slug' => ['', str_repeat('a', 222), 'has_underscore'], 'category_slug' => ['missing-category'],
                'index_position' => [0, -1, 2147483648], 'price_cents' => [0, -1, 2147483648],
                'previous_price_cents' => [2147483648], 'currency' => ['USD'], 'universe' => ['unknown'],
                'index_document' => ['{'], 'detail_document' => ['{'], 'source_name' => [null, str_repeat('a', 65536)]],
            'archive_translations' => ['locale' => ['fr', 'EN'], 'source_key' => [str_repeat('A', 64), str_repeat('a', 63)],
                'source_position' => [0, -1, 2147483648], 'translated_text' => [null]],
        ];
    }

    private function seed(Connection $db, CatalogueArchivePayload $payload): void
    {
        $db->table('archive_lots')->insert($payload->lotRow('2026-10-08 00:00:00.000000'));
        foreach (['archive_categories', 'archive_products', 'archive_translations'] as $table) {
            foreach ($payload->rows($table) as $row) {
                $db->table($table)->insert($row);
            }
        }
    }

    public function integrity(Connection $db): void
    {
        $p = self::payload('integrity');
        $this->rollback($db, function () use ($db, $p): void {
            $this->seed($db, $p);
            foreach (['archive_lots', 'archive_categories', 'archive_products', 'archive_translations'] as $table) {
                $row = $table === 'archive_lots' ? $p->lotRow('2026-10-08 00:00:00.000000') : $p->rows($table)[0];
                $this->rejected($db, 'PK-'.$table, fn () => $db->table($table)->insert($row), [1062]);
            }
            $n = 100;
            foreach (self::mutations() as $table => $columns) {
                foreach ($columns as $column => $values) {
                    foreach ($values as $value) {
                        $n++;
                        $row = $table === 'archive_lots' ? self::payload('parent-a')->lotRow('2026-10-08 00:00:00.000000') : $p->rows($table)[0];
                        if (isset($row['slug'])) {
                            $row['slug'] = 'probe-'.$n;
                        }
                        if (isset($row['index_position'])) {
                            $row['index_position'] = $n;
                        }
                        if (isset($row['source_key'])) {
                            $row['source_key'] = hash('sha256', 'probe-'.$n);
                            $row['source_position'] = $n;
                        }
                        $row[$column] = $value;
                        $this->rejected($db, 'DOMAIN-'.$table.'-'.$column.'-'.$n, fn () => $db->table($table)->insert($row),
                            $column === 'category_slug' ? [1452] : ($value === null ? [1048] : [4025, 1406, 1264, 1292]));
                    }
                }
            }
            foreach (['archive_categories', 'archive_products', 'archive_translations'] as $table) {
                $row = $p->rows($table)[0];
                $row['lot_key'] = str_repeat('e', 64);
                $this->rejected($db, 'FK-MISSING-'.$table, fn () => $db->table($table)->insert($row), [1452]);
            }
            $product = $p->rows('archive_products')[0];
            $product['slug'] = 'unique-position';
            $this->rejected($db, 'PRODUCT-POSITION', fn () => $db->table('archive_products')->insert($product), [1062]);
            $translation = $p->rows('archive_translations')[0];
            $translation['source_key'] = str_repeat('e', 64);
            $this->rejected($db, 'TRANSLATION-POSITION', fn () => $db->table('archive_translations')->insert($translation), [1062]);
            // Positive boundary controls are rolled back with this transaction.
            $product['slug'] = str_repeat('b', 221);
            $product['index_position'] = 2147483647;
            $product['price_cents'] = 2147483647;
            $product['previous_price_cents'] = null;
            $product['reference'] = str_repeat('a', 65535);
            $db->table('archive_products')->insert($product);
            self::ensure($db->table('archive_products')->where('slug', $product['slug'])->value('reference') === $product['reference'], 'TEXT_BOUNDARY_CHANGED');
            $this->evidence[] = ['case' => 'POSITIVE-BOUNDARIES', 'passed' => true];
            $a = self::payload('parent-a');
            $b = self::payload('parent-b');
            $db->table('archive_lots')->insert($a->lotRow('2026-10-08 00:00:00.000000'));
            $db->table('archive_lots')->insert($b->lotRow('2026-10-08 00:00:00.000000'));
            $db->table('archive_categories')->insert($a->rows('archive_categories')[0]);
            $this->rejected($db, 'CROSS-LOT-CATEGORY', fn () => $db->table('archive_products')->insert($b->rows('archive_products')[0]), [1452]);
            $db->table('archive_categories')->insert($b->rows('archive_categories')[0]);
            $db->table('archive_products')->insert($b->rows('archive_products')[0]);
        });
    }

    public function collision(Connection $db): void
    {
        $p = self::payload('hash-collision');
        $this->rollback($db, function () use ($db, $p): void {
            $row = $p->lotRow('2026-10-08 00:00:00.000000');
            $row['archive_document'] = '{"divergent":true}';
            $db->table('archive_lots')->insert($row);
            try {
                $p->assertLotMatches((array) $db->table('archive_lots')->where('lot_key', $p->lotKey())->first());
                throw new RuntimeException('COLLISION_NOT_REFUSED');
            } catch (RuntimeException $e) {
                self::ensure($e->getMessage() === 'SQL_LOT_MISMATCH', 'COLLISION_UNEXPECTED_RESULT');
            }
        });
    }

    public function transport(Connection $db): void
    {
        $p = self::payload('transport');
        $this->rollback($db, function () use ($db, $p): void {
            $row = $p->lotRow('2026-10-08 00:00:00.000000');
            $row['archive_document'] = StrictJson::encode(str_repeat('a', 4194304));
            $row['manifest_document'] = StrictJson::encode(str_repeat('b', 2097152));
            self::ensure($p->packetBudget($row) < 16777216, 'PROBE_BUDGET_EXCEEDED');
            $db->table('archive_lots')->insert($row);
            $actual = (array) $db->table('archive_lots')->where('lot_key', $p->lotKey())->first();
            foreach (['archive_document', 'manifest_document'] as $column) {
                self::ensure($actual[$column] === $row[$column], 'PDO_DOCUMENT_CHANGED');
                $this->evidence[] = ['case' => 'PDO-'.$column, 'bytes' => strlen($row[$column]), 'sha256' => hash('sha256', $actual[$column])];
            }
        });
        self::ensure((new CatalogueArchiveImporter)->load($db, $p)['status'] === 'ARCHIVE_VERIFIED_NOT_ACTIVATED', 'SMALL_IMPORT_FAILED');
        $before = (array) $db->table('archive_lots')->where('lot_key', $p->lotKey())->first();
        self::ensure((new CatalogueArchiveImporter)->load($db, $p)['status'] === 'ARCHIVE_ALREADY_VERIFIED_UNCHANGED', 'SECOND_IMPORT_CHANGED');
        self::ensure($before === (array) $db->table('archive_lots')->where('lot_key', $p->lotKey())->first(), 'VERIFIED_LOT_CHANGED');
    }

    public function permissions(Connection $import, Connection $reader): void
    {
        $p = self::payload('transport');
        $this->rollback($reader, function () use ($reader, $p): void {
            foreach (['archive_lots', 'archive_categories', 'archive_products', 'archive_translations'] as $table) {
                $row = $table === 'archive_lots' ? self::payload('integrity')->lotRow('2026-10-08 00:00:00.000000') : self::payload('integrity')->rows($table)[0];
                $this->rejected($reader, 'READER-INSERT-'.$table, fn () => $reader->table($table)->insert($row), [1142, 1143]);
            }
            $this->rejected($reader, 'READER-UPDATE', fn () => $reader->table('archive_lots')->where('lot_key', $p->lotKey())->update(['load_state' => 'verified']), [1142, 1143]);
        });
        $this->rollback($import, function () use ($import, $p): void {
            foreach (['archive_document', 'lot_key'] as $column) {
                $value = $p->lotRow('2026-10-08 00:00:00.000000')[$column];
                $this->rejected($import, 'IMPORT-UPDATE-'.$column, fn () => $import->table('archive_lots')->where('lot_key', $p->lotKey())->update([$column => $value]), [1142, 1143]);
            }
            foreach (['archive_categories' => 'source_name', 'archive_products' => 'source_brand', 'archive_translations' => 'translated_text'] as $table => $column) {
                $this->rejected($import, 'IMPORT-CHILD-UPDATE-'.$table, fn () => $import->table($table)->where('lot_key', $p->lotKey())->update([$column => 'synthetic']), [1142, 1143]);
            }
        });
        foreach ([$import, $reader] as $db) {
            try {
                $db->table('migrations')->get();
                throw new RuntimeException('JOURNAL_ACCESS_UNEXPECTED');
            } catch (QueryException $e) {
                self::ensure(in_array((int) ($e->errorInfo[1] ?? 0), [1142, 1143], true), 'JOURNAL_REFUSAL_UNEXPECTED');
            }
        }
    }

    public function failureAndPartial(Connection $db, Connection $reader): void
    {
        $checks = 0;
        $p = self::instrumentedPayload('failure', function () use (&$checks): void {
            if (++$checks === 2) {
                throw new RuntimeException('SYNTHETIC_PRECOMMIT_FAILURE');
            }
        });
        try {
            (new CatalogueArchiveImporter)->load($db, $p);
            throw new RuntimeException('FAILURE_NOT_INJECTED');
        } catch (RuntimeException $e) {
            self::ensure($e->getMessage() === 'IMPORT_FAILED_NOT_ACTIVATED', 'FAILURE_UNEXPECTED_RESULT');
        }
        $lot = $db->table('archive_lots')->where('lot_key', $p->lotKey())->first();
        self::ensure($lot->load_state === 'failed' && $lot->verified_at === null && $lot->verification_document === null, 'FAILED_STATE_INVALID');
        foreach (['archive_categories', 'archive_products', 'archive_translations'] as $table) {
            self::ensure($db->table($table)->where('lot_key', $p->lotKey())->count() === 0, 'FAILED_CHILDREN_PERSISTED');
        }
        $partial = self::payload('partial');
        $db->table('archive_lots')->insert($partial->lotRow('2026-10-08 00:00:00.000000'));
        $before = (array) $db->table('archive_lots')->where('lot_key', $partial->lotKey())->first();
        try {
            (new CatalogueArchiveImporter)->load($db, $partial);
            throw new RuntimeException('PARTIAL_RESUMED');
        } catch (RuntimeException $e) {
            self::ensure($e->getMessage() === 'LOT_NOT_VERIFIED', 'PARTIAL_UNEXPECTED_RESULT');
        }
        self::ensure($before === (array) $db->table('archive_lots')->where('lot_key', $partial->lotKey())->first(), 'PARTIAL_CHANGED');
        foreach ([$p, $partial] as $unavailable) {
            try {
                (new CatalogueArchiveStore($reader, $unavailable->lotKey()))->snapshot();
                throw new RuntimeException('INCOMPLETE_READABLE');
            } catch (RuntimeException $e) {
                self::ensure($e->getMessage() === 'LOT_UNAVAILABLE', 'READER_UNEXPECTED_RESULT');
            }
        }
        $this->evidence[] = ['case' => 'FAILURE-PARTIAL', 'failed_children' => 0, 'partial_unchanged' => true];
    }
}
