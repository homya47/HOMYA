<?php

namespace Tests\Unit;

use App\Console\Commands\ImportHostingerCatalogue;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ImportHostingerCatalogueTest extends TestCase
{
    public function test_mysqldump_parser_keeps_semicolons_inside_values_and_executable_comments(): void
    {
        $sql = "/*!40101 SET NAMES utf8mb4 */;\nCREATE TABLE `archive_products` (`name` text);\nINSERT INTO `archive_products` VALUES ('semi;colon', 'it''s fine');\n";
        $statements = ImportHostingerCatalogue::splitSqlStatements($sql);

        $this->assertCount(3, $statements);
        ImportHostingerCatalogue::requireApprovedStatements($statements);
    }

    public function test_dump_parser_rejects_unclosed_literals(): void
    {
        $this->expectException(RuntimeException::class);
        ImportHostingerCatalogue::splitSqlStatements("INSERT INTO `archive_products` VALUES ('unfinished);");
    }

    public function test_import_whitelist_refuses_destructive_or_unrelated_statements(): void
    {
        foreach (["DROP TABLE `archive_products`", "INSERT INTO `customers` VALUES (1)"] as $sql) {
            try {
                ImportHostingerCatalogue::requireApprovedStatements([$sql]);
                $this->fail('Expected the operation whitelist to refuse this statement.');
            } catch (RuntimeException) {
                $this->assertTrue(true);
            }
        }
    }
}
