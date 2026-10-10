<?php

namespace App\Console\Commands;

use App\Services\CatalogueReadProfile;
use App\Services\PrivateLocalFiles;
use App\Services\ProductionSecurity;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/** One-time import of the exact reviewed archive into an empty Hostinger database. */
final class ImportHostingerCatalogue extends Command
{
    public const DUMP_SHA256 = 'cb67ffd9c66cfd0b12bbbe0c7fe0acb0a211cf197557c2030a46aed841b1febd';

    private const TABLES = ['archive_lots', 'archive_categories', 'archive_products', 'archive_translations', 'migrations'];

    protected $signature = 'homya:hostinger-catalogue-import {--apply : Execute the exact reviewed SQL import into the verified empty database}';

    protected $description = 'Import the reviewed HOMYA archive into an empty Hostinger MariaDB database; never activates it.';

    public function handle(): int
    {
        try {
            $connection = $this->target();
            $profile = CatalogueReadProfile::loadApproved();
            $private = PrivateLocalFiles::path(base_path(), 'storage/app/private');
            PrivateLocalFiles::requireMode($private, 0700, true);
            $directory = PrivateLocalFiles::path(base_path(), 'storage/app/private/hostinger-import');
            PrivateLocalFiles::requireMode($directory, 0700, true);
            $path = PrivateLocalFiles::path(base_path(), 'storage/app/private/hostinger-import/catalogue.sql');
            $this->requirePrivateDump($path);
            if (! hash_equals(self::DUMP_SHA256, hash_file('sha256', $path))) {
                throw new RuntimeException('HOSTED_DUMP_DIGEST_INVALID');
            }
            $existingTables = $this->tableNames($connection);
            if ($existingTables !== []) {
                if (! $this->option('apply')) {
                    throw new RuntimeException('HOSTED_DATABASE_MUST_BE_EMPTY');
                }

                // MariaDB DDL is not transactional. A previous run may have completed
                // all statements and failed only in final verification. Verify the
                // approved lot and its scoped row counts before reporting success;
                // never replay SQL or repair/delete existing rows automatically.
                $this->verifyImport($connection, $profile->data['counts']);
                $this->line(json_encode(['status' => 'HOSTINGER_ARCHIVE_IMPORTED_VERIFIED_NOT_ACTIVATED',
                    'lot_key' => CatalogueReadProfile::LOT, 'counts' => $profile->data['counts'],
                    'sql_executed' => false, 'already_imported' => true, 'catalogue_activated' => false], JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }
            $statements = self::splitSqlStatements(file_get_contents($path));
            self::requireApprovedStatements($statements);
            if (! $this->option('apply')) {
                $this->line(json_encode(['status' => 'HOSTINGER_IMPORT_READY_NO_SQL', 'lot_key' => CatalogueReadProfile::LOT,
                    'products' => $profile->data['counts']['products'], 'categories' => $profile->data['counts']['categories'],
                    'translations' => $profile->data['counts']['translations'], 'statements' => count($statements), 'sql_executed' => false], JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }
            foreach ($statements as $statement) {
                $connection->unprepared($statement);
            }
            $this->verifyImport($connection, $profile->data['counts']);
            $this->line(json_encode(['status' => 'HOSTINGER_ARCHIVE_IMPORTED_VERIFIED_NOT_ACTIVATED',
                'lot_key' => CatalogueReadProfile::LOT, 'counts' => $profile->data['counts'],
                'sql_executed' => true, 'already_imported' => false, 'catalogue_activated' => false], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Import refusé ou interrompu. Aucun nettoyage ni nouvel essai automatique ; contrôler l’état des tables avant de reprendre.');

            return self::FAILURE;
        }
    }

    private function target(): Connection
    {
        if (! app()->environment('production') || config('app.debug') !== false
            || config('app.url') !== 'https://homya.eu' || ! extension_loaded('pdo_mysql')
            || app(ProductionSecurity::class)->failures() !== []) {
            throw new RuntimeException('HOSTINGER_PRODUCTION_GUARD_FAILED');
        }
        $config = config('database.connections.mariadb');
        if (! is_array($config) || ! in_array($config['host'] ?? null, ['localhost', '127.0.0.1'], true)
            || (string) ($config['port'] ?? '') !== '3306' || ! is_string($config['database'] ?? null)
            || $config['database'] === '' || ! is_string($config['username'] ?? null)
            || $config['username'] === '' || $config['username'] === 'root') {
            throw new RuntimeException('HOSTINGER_DATABASE_TARGET_INVALID');
        }

        return DB::connection('mariadb');
    }

    private function requirePrivateDump(string $path): void
    {
        if (! is_file($path) || is_link($path) || (fileperms($path) & 0077) !== 0 || filesize($path) > 50_000_000) {
            throw new RuntimeException('HOSTED_DUMP_PRIVATE_FILE_REQUIRED');
        }
    }

    private function tableNames(Connection $connection): array
    {
        $tables = $connection->select('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME', [$connection->getDatabaseName()]);

        return array_map(static fn (object $table): string => (string) $table->TABLE_NAME, $tables);
    }

    public static function requireApprovedStatements(array $statements): void
    {
        if ($statements === []) {
            throw new RuntimeException('HOSTED_DUMP_EMPTY');
        }
        foreach ($statements as $statement) {
            $sql = preg_replace_callback('/\/\*![0-9]*\s*([\s\S]*?)\*\//', static fn (array $match) => $match[1], $statement);
            $sql = preg_replace('/\A(?:\s|--[^\r\n]*(?:\r?\n|$)|\#[^\r\n]*(?:\r?\n|$)|\/\*[\s\S]*?\*\/)+/', '', $sql);
            if (preg_match('/\A(?:CREATE TABLE|INSERT INTO|ALTER TABLE)\s+`([a-z_]+)`/i', $sql, $match)) {
                if (! in_array(strtolower($match[1]), self::TABLES, true)) {
                    throw new RuntimeException('HOSTED_DUMP_TABLE_INVALID');
                }
                continue;
            }
            if (preg_match('/\A(?:SET\b|LOCK TABLES\b|UNLOCK TABLES\b)/i', $sql)) {
                continue;
            }
            throw new RuntimeException('HOSTED_DUMP_OPERATION_INVALID');
        }
    }

    /** Split a mysqldump file without splitting quoted values or comments. */
    public static function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $lineComment = false;
        $blockComment = false;
        $escaped = false;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';
            if ($lineComment) {
                $buffer .= $char;
                if ($char === "\n") {
                    $lineComment = false;
                }
                continue;
            }
            if ($blockComment) {
                $buffer .= $char;
                if ($char === '*' && $next === '/') {
                    $buffer .= '/';
                    $i++;
                    $blockComment = false;
                }
                continue;
            }
            if ($quote !== null) {
                $buffer .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\' && $quote !== '`') {
                    $escaped = true;
                } elseif ($char === $quote) {
                    if ($next === $quote) {
                        $buffer .= $next;
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($char === '/' && $next === '*') {
                $buffer .= '/*';
                $i++;
                $blockComment = true;
                continue;
            }
            if ($char === '#' || ($char === '-' && $next === '-' && (($sql[$i + 2] ?? ' ') <= ' '))) {
                $buffer .= $char;
                if ($char === '-') {
                    $buffer .= '-';
                    $i++;
                }
                $lineComment = true;
                continue;
            }
            if (in_array($char, ["'", '"', '`'], true)) {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === ';') {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        if ($quote !== null || $blockComment) {
            throw new RuntimeException('HOSTED_DUMP_SYNTAX_INCOMPLETE');
        }
        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }

    private function verifyImport(Connection $connection, array $counts): void
    {
        $actualTables = $this->tableNames($connection);
        $expectedTables = self::TABLES;
        sort($actualTables);
        sort($expectedTables);
        if ($actualTables !== $expectedTables) {
            throw new RuntimeException('HOSTED_IMPORT_TABLES_DIVERGENT');
        }
        $approvedProfile = CatalogueReadProfile::loadApproved()->data;
        $lot = $connection->table('archive_lots')->where('lot_key', CatalogueReadProfile::LOT)->first();
        if ($lot === null || $lot->load_state !== 'verified' || hash('sha256', $lot->manifest_document) !== CatalogueReadProfile::LOT
            || hash('sha256', $lot->archive_document) !== $approvedProfile['archive_sha256']
            || hash('sha256', $lot->verification_document) !== $approvedProfile['proof_sha256']) {
            throw new RuntimeException('HOSTED_IMPORT_LOT_DIVERGENT');
        }
        $actual = ['lots' => $connection->table('archive_lots')->where('lot_key', CatalogueReadProfile::LOT)->count(),
            'categories' => $connection->table('archive_categories')->where('lot_key', CatalogueReadProfile::LOT)->count(),
            'products' => $connection->table('archive_products')->where('lot_key', CatalogueReadProfile::LOT)->count(),
            'translations' => $connection->table('archive_translations')->where('lot_key', CatalogueReadProfile::LOT)->count()];
        foreach ($actual as $key => $count) {
            if ($count !== $counts[$key]) {
                throw new RuntimeException('HOSTED_IMPORT_COUNT_DIVERGENT');
            }
        }
    }
}
