<?php

namespace Tests\MariaDB;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Audits a completed envelope run, never launches writes or cleans a database. */
class CatalogueArchiveWriteRecipeTest extends TestCase
{
    public function test_completed_authorised_write_recipe_has_independent_admin_proof(): void
    {
        if (getenv('HOMYA_RUN_ARCHIVE_RECIPE') !== 'OP-06') {
            $this->markTestSkipped('Recette OP-06 désactivée avant tout accès.');
        }
        $root = dirname(__DIR__, 2);
        require_once $root.'/scripts/op06_common.php';
        $receipt = getenv('HOMYA_RECIPE_AUTHORIZATION');
        $run = getenv('HOMYA_RECIPE_RUN_DIR');
        if (! is_string($receipt) || ! is_string($run)) {
            throw new RuntimeException('RECIPE_SCOPE_REQUIRED');
        }
        \op06_receipt($receipt);
        $data = \op06_admin(['--audit-final', '--receipt='.$receipt, '--run-dir='.$run]);
        $this->assertSame(['archive_lots' => 5, 'archive_categories' => 67, 'archive_products' => 3711, 'archive_translations' => 23890], $data['counts']);
        $this->assertCount(36, $data['metadata']['privileges']);
        $this->assertCount(3, $data['metadata']['column_privileges']);
    }
}
