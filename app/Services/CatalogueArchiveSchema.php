<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\Schema\Blueprint;
use RuntimeException;

/** One definition for offline DDL and future, separately authorised migrations. */
class CatalogueArchiveSchema
{
    public const TABLES = ['archive_lots', 'archive_categories', 'archive_products', 'archive_translations'];

    public const MIGRATIONS = [
        '2026_10_08_000001_create_archive_lots.php',
        '2026_10_08_000002_create_archive_categories.php',
        '2026_10_08_000003_create_archive_products.php',
        '2026_10_08_000004_create_archive_translations.php',
    ];

    private ?Connection $executionConnection = null;

    private ?string $expectedDdlHash = null;

    public static function forExecution(Connection $connection, string $expectedDdlHash): self
    {
        $schema = new self;
        $schema->executionConnection = $connection;
        $schema->expectedDdlHash = $expectedDdlHash;

        return $schema;
    }

    public function statements(): array
    {
        $offline = new MariaDbConnection(fn () => throw new RuntimeException('OFFLINE_PDO_FORBIDDEN'), 'homya_offline', '', [
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin', 'engine' => 'InnoDB',
        ]);
        $offline->useDefaultSchemaGrammar();
        $result = [];
        foreach (self::TABLES as $table) {
            $blueprint = new Blueprint($offline, $table);
            $blueprint->create();
            $blueprint->engine = 'InnoDB';
            $blueprint->charset = 'utf8mb4';
            $blueprint->collation = 'utf8mb4_bin';
            $this->columns($blueprint, $table);
            $result[$table] = $blueprint->toSql();
            foreach ($this->checks($table) as $name => $expression) {
                $result[$table][] = "alter table `$table` add constraint `$name` check ($expression)";
            }
        }

        return $result;
    }

    public function ddl(): string
    {
        return implode(";\n", array_merge(...array_values($this->statements()))).";\n";
    }

    public function fileDigest(): string
    {
        $directory = PrivateLocalFiles::path(base_path(), 'database/migrations/catalogue');
        $found = array_values(array_diff(scandir($directory), ['.', '..']));
        if ($found !== self::MIGRATIONS) {
            throw new RuntimeException('MIGRATION_DIRECTORY_CHANGED');
        }
        $paths = array_merge(array_map(fn ($name) => 'database/migrations/catalogue/'.$name, self::MIGRATIONS), [
            'app/Services/CatalogueArchiveSchema.php', 'app/Services/ArchiveDatabaseGuard.php', 'app/Services/PrivateLocalFiles.php',
            'app/Console/Commands/PrepareCatalogueSchema.php', 'config/database.php', 'config/homya.php', 'composer.lock',
        ]);
        $hashes = [];
        foreach ($paths as $relative) {
            $hashes[$relative] = hash_file('sha256', PrivateLocalFiles::path(base_path(), $relative));
        }

        return hash('sha256', StrictJson::encode($hashes));
    }

    /** No default execution context: loading/calling a migration alone fails closed. */
    public function apply(string $table): void
    {
        if ($this->executionConnection === null || ! in_array($table, self::TABLES, true)
            || $this->expectedDdlHash !== hash('sha256', $this->ddl())) {
            throw new RuntimeException('SCHEMA_EXECUTION_CONTEXT_REQUIRED');
        }
        app(ArchiveDatabaseGuard::class)->validateConnectedRole($this->executionConnection, 'schema');
        if ($this->executionConnection->transactionLevel() !== 0) {
            throw new RuntimeException('DDL_TRANSACTION_FORBIDDEN');
        }
        foreach ($this->statements()[$table] as $statement) {
            $this->executionConnection->statement($statement);
        }
    }

    private function ascii(Blueprint $t, string $name, int $length, bool $fixed = false): void
    {
        ($fixed ? $t->char($name, $length) : $t->string($name, $length))->charset('ascii')->collation('ascii_bin');
    }

    private function columns(Blueprint $t, string $table): void
    {
        $this->ascii($t, 'lot_key', 64, true);
        if ($table === 'archive_lots') {
            $t->integer('format_version');
            $t->json('manifest_document');
            $t->json('archive_document');
            $this->ascii($t, 'load_state', 16);
            $t->json('verification_document')->nullable();
            $t->dateTime('registered_at', 6);
            $t->dateTime('verified_at', 6)->nullable();
            $t->primary('lot_key', 'homya_lots_pk');

            return;
        }
        if ($table === 'archive_translations') {
            $this->ascii($t, 'locale', 2, true);
            $this->ascii($t, 'source_key', 64, true);
            $t->text('source_text')->collation('utf8mb4_bin');
            $t->text('translated_text')->collation('utf8mb4_bin');
            $t->integer('source_position');
            $t->primary(['lot_key', 'locale', 'source_key'], 'homya_translations_pk');
            $t->unique(['lot_key', 'locale', 'source_position'], 'homya_translations_position_uq');
        } else {
            $this->ascii($t, 'slug', 221);
            if ($table === 'archive_products') {
                $this->ascii($t, 'category_slug', 221);
                $t->integer('index_position');
                foreach (['reference', 'source_name', 'source_brand', 'source_condition'] as $column) {
                    $t->text($column)->collation('utf8mb4_bin');
                }
                $this->ascii($t, 'universe', 32);
                $t->integer('price_cents');
                $t->integer('previous_price_cents')->nullable();
                $this->ascii($t, 'currency', 3, true);
                $t->json('index_document');
                $t->json('detail_document');
                $t->unique(['lot_key', 'index_position'], 'homya_products_position_uq');
                $t->index(['lot_key', 'category_slug'], 'homya_products_category_idx');
                $t->foreign(['lot_key', 'category_slug'], 'homya_products_category_fk')
                    ->references(['lot_key', 'slug'])->on('archive_categories')->restrictOnDelete()->restrictOnUpdate();
            } else {
                $t->text('source_name')->collation('utf8mb4_bin');
            }
            $t->primary(['lot_key', 'slug'], 'homya_'.substr($table, 8).'_pk');
        }
        $t->foreign('lot_key', 'homya_'.substr($table, 8).'_lot_fk')->references('lot_key')->on('archive_lots')
            ->restrictOnDelete()->restrictOnUpdate();
    }

    private function checks(string $table): array
    {
        $prefix = 'homya_'.substr($table, 8);
        $checks = [$prefix.'_key_ck' => "octet_length(lot_key) = 64 and lot_key regexp '^[a-f0-9]{64}$'"];
        if ($table === 'archive_lots') {
            $checks += [
                $prefix.'_version_ck' => 'format_version = 1',
                $prefix.'_state_ck' => "binary load_state in ('prepared','loading','verified','failed')",
                $prefix.'_proof_ck' => "(load_state = 'verified' and verification_document is not null and verified_at is not null) or (load_state <> 'verified' and verification_document is null and verified_at is null)",
            ];
            $jsonColumns = ['manifest_document', 'archive_document', 'verification_document'];
        } elseif ($table === 'archive_translations') {
            $checks += [
                $prefix.'_source_key_ck' => "octet_length(source_key) = 64 and source_key regexp '^[a-f0-9]{64}$'",
                $prefix.'_locale_ck' => "locale in ('en','de')",
                $prefix.'_position_ck' => 'source_position > 0',
            ];
            $jsonColumns = [];
        } else {
            $checks[$prefix.'_slug_ck'] = "octet_length(slug) between 1 and 221 and slug regexp '^[a-z0-9][a-z0-9-]{0,220}$' and slug not regexp '[^a-z0-9-]'";
            $jsonColumns = [];
            if ($table === 'archive_products') {
                $checks += [
                    $prefix.'_position_ck' => 'index_position > 0',
                    $prefix.'_price_ck' => 'price_cents > 0',
                    $prefix.'_currency_ck' => "currency = 'EUR'",
                    $prefix.'_universe_ck' => "binary universe in ('appliances','firewood','furniture','heating','televisions')",
                    $prefix.'_category_ck' => "octet_length(category_slug) between 1 and 221 and category_slug regexp '^[a-z0-9][a-z0-9-]{0,220}$' and category_slug not regexp '[^a-z0-9-]'",
                ];
                $jsonColumns = ['index_document', 'detail_document'];
            }
        }
        foreach ($jsonColumns as $column) {
            $checks[$prefix.'_'.$column.'_ck'] = "`$column` is null or json_valid(`$column`)";
        }

        return $checks;
    }
}
