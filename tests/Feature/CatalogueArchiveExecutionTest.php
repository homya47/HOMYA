<?php

namespace Tests\Feature;

use App\Services\CatalogueArchiveImporter;
use App\Services\CatalogueArchiveStore;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\ArchiveConnectionDouble;
use Tests\Support\CatalogueArchiveFixture;
use Tests\Support\CatalogueCapsuleFixture;
use Tests\Support\Op06Recipe;
use Tests\TestCase;

class CatalogueArchiveExecutionTest extends TestCase
{
    private CatalogueCapsuleFixture $fixture;

    private $socket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->detectEnvironment(fn () => 'local');
        $this->fixture = new CatalogueCapsuleFixture;
        $path = $this->fixture->root.'/db.sock';
        $this->socket = stream_socket_server('unix://'.$path);
        $this->assertIsResource($this->socket);
        config(['homya.xampp_socket' => $path, 'database.connections.mariadb.url' => null]);
        foreach (['reader', 'import', 'schema'] as $role) {
            config(['database.connections.homya_test_'.$role.'.unix_socket' => $path]);
        }
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
    }

    protected function tearDown(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        $this->fixture->destroy();
        parent::tearDown();
    }

    public function test_atomic_import_and_read_only_reconstruction_use_real_query_builder_without_pdo(): void
    {
        $p = CatalogueArchiveFixture::payload();
        $db = new ArchiveConnectionDouble;
        $result = (new CatalogueArchiveImporter)->load($db, $p);
        $this->assertSame('ARCHIVE_VERIFIED_NOT_ACTIVATED', $result['status']);
        $this->assertFalse($result['activated']);
        $this->assertSame('verified', $db->rows['archive_lots'][0]['load_state']);
        foreach ($db->operations as $event) {
            if ($event[0] === 'insert' && $event[1] !== 'archive_lots') {
                $this->assertSame(1, $event[2]);
                $this->assertSame($p->lotKey(), $event[3]);
            }
        }
        $before = $db->rows;
        $again = (new CatalogueArchiveImporter)->load($db, $p);
        $this->assertSame('ARCHIVE_ALREADY_VERIFIED_UNCHANGED', $again['status']);
        $this->assertSame($before, $db->rows);
        $reader = new ArchiveConnectionDouble('reader');
        $reader->rows = $db->rows;
        $store = new CatalogueArchiveStore($reader, $p->lotKey());
        $this->assertSame($p->digest(), $store->snapshot()->digest());
        $this->assertSame('Échantillon €', $store->product('fixture')->name);
        $this->assertNull($store->product('not-found'));
        $this->assertSame('Numeric string', $store->dictionary('en')->strings->{'1'});
        $this->assertFalse($store->dictionary('de')->human_review);
        $this->assertSame($before, $reader->rows);
        $this->assertSame([], array_values(array_filter($reader->operations, fn ($e) => in_array($e[0], ['update', 'insert'], true))));
        try {
            $db->reconnect();
            $this->fail('Every importer entry must install its connection guard.');
        } catch (RuntimeException $e) {
            $this->assertSame('ARCHIVE_RECONNECT_FORBIDDEN', $e->getMessage());
        }
    }

    public function test_recipe_failure_and_partial_scenarios_preserve_only_expected_parents_without_pdo(): void
    {
        $db = new ArchiveConnectionDouble;
        $reader = new ArchiveConnectionDouble('reader');
        $reader->rows = &$db->rows;
        (new Op06Recipe)->failureAndPartial($db, $reader);
        $this->assertSame(['failed', 'prepared'], array_column($db->rows['archive_lots'], 'load_state'));
        foreach (['archive_categories', 'archive_products', 'archive_translations'] as $table) {
            $this->assertSame([], $db->rows[$table]);
        }
    }

    public function test_child_failure_rolls_back_all_children_and_marks_only_own_prepared_lot_failed(): void
    {
        $p = CatalogueArchiveFixture::payload();
        $db = new ArchiveConnectionDouble;
        $db->failInsertTable = 'archive_translations';
        try {
            (new CatalogueArchiveImporter)->load($db, $p);
            $this->fail('Import must fail.');
        } catch (RuntimeException $e) {
            $this->assertSame('IMPORT_FAILED_NOT_ACTIVATED', $e->getMessage());
        }
        $this->assertSame('failed', $db->rows['archive_lots'][0]['load_state']);
        foreach (['archive_categories', 'archive_products', 'archive_translations'] as $table) {
            $this->assertSame([], $db->rows[$table]);
        }
        $this->assertNull($db->rows['archive_lots'][0]['verified_at']);
        $this->assertSame(0, $db->transactionLevel());
    }

    public function test_initial_insert_runtime_failure_is_reported_uncertain_without_retry(): void
    {
        $db = new ArchiveConnectionDouble;
        $db->failInsertTable = 'archive_lots';
        try {
            (new CatalogueArchiveImporter)->load($db, CatalogueArchiveFixture::payload());
            $this->fail('Uncertain registration must stop.');
        } catch (RuntimeException $e) {
            $this->assertSame('LOT_REGISTRATION_STATUS_TO_INSPECT', $e->getMessage());
        }
        $this->assertSame(1, count(array_filter($db->operations, fn ($e) => $e[0] === 'insert')));
        $this->assertNotContains(['begin'], $db->operations);
    }

    public function test_uncertain_commit_is_not_replayed_or_marked_failed(): void
    {
        $p = CatalogueArchiveFixture::payload();
        $db = new ArchiveConnectionDouble;
        $db->failCommit = true;
        try {
            (new CatalogueArchiveImporter)->load($db, $p);
            $this->fail('Commit must be reported uncertain.');
        } catch (RuntimeException $e) {
            $this->assertSame('IMPORT_COMMIT_STATUS_TO_INSPECT', $e->getMessage());
        }
        $this->assertSame('verified', $db->rows['archive_lots'][0]['load_state']);
        $this->assertSame(1, count(array_filter($db->operations, fn ($e) => $e[0] === 'commit')));
        $this->assertNotContains(['rollback'], $db->operations);
    }

    public function test_existing_incomplete_lot_cannot_be_resumed_or_read(): void
    {
        $p = CatalogueArchiveFixture::payload();
        $db = new ArchiveConnectionDouble;
        $db->rows['archive_lots'][] = $p->lotRow('2026-10-08 00:00:00.000000');
        $before = $db->rows;
        try {
            (new CatalogueArchiveImporter)->load($db, $p);
            $this->fail('Incomplete lot cannot be resumed.');
        } catch (RuntimeException $e) {
            $this->assertSame('LOT_NOT_VERIFIED', $e->getMessage());
        }
        $this->assertSame($before, $db->rows);
        $reader = new ArchiveConnectionDouble('reader');
        $reader->rows = $db->rows;
        $this->expectExceptionMessage('LOT_UNAVAILABLE');
        (new CatalogueArchiveStore($reader, $p->lotKey()))->snapshot();
    }

    public function test_failure_never_downgrades_another_verified_lot(): void
    {
        $p = CatalogueArchiveFixture::payload();
        $db = new ArchiveConnectionDouble;
        $db->failInsertTable = 'archive_products';
        $db->beforeFailedTransition = function ($connection) use ($p): void {
            $connection->rows['archive_lots'] = [CatalogueArchiveFixture::verifiedLot($p)];
            foreach (['archive_categories', 'archive_products', 'archive_translations'] as $table) {
                $connection->rows[$table] = $p->rows($table);
            }
        };
        try {
            (new CatalogueArchiveImporter)->load($db, $p);
            $this->fail('Unsafe failed transition must stop.');
        } catch (RuntimeException $e) {
            $this->assertSame('IMPORT_STATUS_TO_INSPECT', $e->getMessage());
        }
        $this->assertSame('verified', $db->rows['archive_lots'][0]['load_state']);
        $this->assertSame($p->proof(), $db->rows['archive_lots'][0]['verification_document']);
    }

    public function test_wrong_role_fails_before_first_query(): void
    {
        $db = new ArchiveConnectionDouble('reader');
        try {
            (new CatalogueArchiveImporter)->load($db, CatalogueArchiveFixture::payload());
            $this->fail('Wrong role must fail.');
        } catch (RuntimeException $e) {
            $this->assertSame('CONNECTION_ROLE_INVALID', $e->getMessage());
        }
        $this->assertSame([], $db->operations);
        $this->expectExceptionMessage('CONNECTION_ROLE_INVALID');
        new CatalogueArchiveStore(new ArchiveConnectionDouble('import'), str_repeat('a', 64));
    }

    public function test_equal_row_counts_with_corrupt_data_are_not_readable(): void
    {
        $p = CatalogueArchiveFixture::payload();
        $db = new ArchiveConnectionDouble('reader');
        $db->rows['archive_lots'][] = CatalogueArchiveFixture::verifiedLot($p);
        foreach (['archive_categories', 'archive_products', 'archive_translations'] as $table) {
            $db->rows[$table] = $p->rows($table);
        }
        $db->rows['archive_products'][0]['price_cents']++;
        $this->expectExceptionMessage('SQL_VALUE_MISMATCH');
        (new CatalogueArchiveStore($db, $p->lotKey()))->snapshot();
    }
}
