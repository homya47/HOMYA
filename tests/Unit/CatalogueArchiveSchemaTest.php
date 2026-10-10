<?php

namespace Tests\Unit;

use App\Services\CatalogueArchiveSchema;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class CatalogueArchiveSchemaTest extends TestCase
{
    public function test_schema_compiles_deterministically_without_pdo(): void
    {
        $schema = new CatalogueArchiveSchema;
        $ddl = $schema->ddl();
        $this->assertSame($ddl, (new CatalogueArchiveSchema)->ddl());
        $this->assertSame(4, substr_count($ddl, 'create table '));
        $this->assertSame(CatalogueArchiveSchema::TABLES, array_keys($schema->statements()));
        foreach (['unsigned', 'cascade', 'drop ', 'users', 'cache', 'jobs', 'migrations', 'AUTO_INCREMENT'] as $absent) {
            $this->assertStringNotContainsString($absent, $ddl);
        }
        foreach (['datetime(6)', 'char(64) character set ascii', 'varchar(221)', 'utf8mb4_bin', 'engine = InnoDB',
            'foreign key (`lot_key`, `category_slug`)', 'unique `homya_products_position_uq`', 'json_valid', 'previous_price_cents` int null'] as $present) {
            $this->assertStringContainsString($present, $ddl);
        }
        preg_match_all('/add constraint `([^`]+)`/', $ddl, $m);
        $this->assertSame(count($m[1]), count(array_unique($m[1])));
        foreach ($m[1] as $name) {
            $this->assertLessThanOrEqual(64, strlen($name));
        }
    }

    public function test_execution_without_explicit_context_is_refused(): void
    {
        $this->expectExceptionMessage('SCHEMA_EXECUTION_CONTEXT_REQUIRED');
        (new CatalogueArchiveSchema)->apply('archive_lots');
    }

    public function test_migration_files_are_exact_and_down_is_non_destructive(): void
    {
        $dir = dirname(__DIR__, 2).'/database/migrations/catalogue/';
        $files = array_map('basename', glob($dir.'*.php'));
        $this->assertSame(CatalogueArchiveSchema::MIGRATIONS, $files);
        foreach ($files as $file) {
            $migration = require $dir.$file;
            $this->assertFalse($migration->withinTransaction);
            try {
                $migration->down();
                $this->fail('Rollback must refuse destruction.');
            } catch (RuntimeException $e) {
                $this->assertSame('DESTRUCTIVE_ROLLBACK_NOT_AUTHORISED', $e->getMessage());
            }
        }
    }
}
