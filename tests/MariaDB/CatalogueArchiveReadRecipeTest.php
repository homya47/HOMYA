<?php

namespace Tests\MariaDB;

use App\Services\ArchiveDatabaseGuard;
use App\Services\CatalogueArchivePayload;
use App\Services\CatalogueArchiveStore;
use App\Services\CatalogueCapsule;
use App\Services\PrivateLocalFiles;
use App\Services\StrictJson;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Not discovered by phpunit.xml; defaults to SKIPPED before boot or credential loading. */
class CatalogueArchiveReadRecipeTest extends TestCase
{
    protected function tearDown(): void
    {
        HandleExceptions::flushState($this);
        parent::tearDown();
    }

    public function test_explicit_readonly_reconciliation_of_authorised_test_lot(): void
    {
        if (getenv('HOMYA_RUN_ARCHIVE_RECIPE') !== 'OP-06') {
            $this->markTestSkipped('Recette MariaDB désactivée : autorisation OP-06 distincte requise.');
        }
        $root = dirname(__DIR__, 2);
        $key = getenv('HOMYA_RECIPE_EXPECTED_LOT');
        $receipt = getenv('HOMYA_RECIPE_AUTHORIZATION');
        if (! is_string($key) || ! preg_match('/^[a-f0-9]{64}$/D', $key) || ! is_string($receipt)
            || ! str_starts_with($receipt, $root.'/output/qa-import/')) {
            throw new RuntimeException('RECIPE_SCOPE_REQUIRED');
        }
        require_once $root.'/scripts/op06_common.php';
        \op06_receipt($receipt); // Offline, immutable artifact/plan validation before boot.
        $path = PrivateLocalFiles::path($root, substr($receipt, strlen($root) + 1));
        PrivateLocalFiles::requireMode($path, 0600);
        $scope = StrictJson::decode(file_get_contents($path));
        if (($scope['operation'] ?? null) !== 'OP-06' || ($scope['schema'] ?? null) !== 'homya_test'
            || ($scope['lot_key'] ?? null) !== $key || ($scope['sql_read_recipe_authorized'] ?? null) !== true) {
            throw new RuntimeException('RECIPE_NOT_AUTHORISED');
        }
        $app = require $root.'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        $guard = new ArchiveDatabaseGuard;
        $configuration = $guard->configurationForRole('homya_test_reader', (string) getenv('HOMYA_TEST_READER_ACCESS'), 'reader');
        try {
            $connection = DB::connectUsing('homya_archive_read_recipe', $configuration, true);
            unset($configuration);
            $expected = CatalogueArchivePayload::fromCapsule(new CatalogueCapsule, $key);
            $actual = (new CatalogueArchiveStore($connection, $key))->snapshot();
            $this->assertSame($expected->digest(), $actual->digest());
            $this->assertSame($expected->counts(), $actual->counts());
            $expected->verifyFiles();
        } finally {
            DB::purge('homya_archive_read_recipe');
        }
    }
}
