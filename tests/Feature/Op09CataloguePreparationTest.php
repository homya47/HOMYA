<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\ArchiveConnectionDouble;
use Tests\Support\CatalogueArchiveFixture;
use Tests\Support\CatalogueCapsuleFixture;
use Tests\TestCase;

class Op09CataloguePreparationTest extends TestCase
{
    private CatalogueCapsuleFixture $fixture;

    private $socket;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2).'/scripts/op09_catalogue.php';
        $this->app->detectEnvironment(fn () => 'local');
        $this->fixture = new CatalogueCapsuleFixture;
        $path = $this->fixture->root.'/op09.sock';
        $this->socket = stream_socket_server('unix://'.$path);
        $this->assertIsResource($this->socket);
        config(['homya.xampp_socket' => $path, 'database.connections.mariadb.url' => null]);
        foreach (['reader', 'import'] as $role) {
            config(['database.connections.homya_local_'.$role.'.unix_socket' => $path]);
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

    public function test_new_local_helper_imports_and_reconciles_without_pdo_or_activation(): void
    {
        $expected = CatalogueArchiveFixture::payload();
        $import = new Op09LocalDouble;
        $result = \op09Reconcile($import, $expected, true);
        $this->assertSame('ARCHIVE_VERIFIED_NOT_ACTIVATED', $result['status']);
        $this->assertSame($expected->digest(), $result['payload_sha256']);
        $this->assertFalse($result['activated']);
        $this->assertSame('verified', $import->rows['archive_lots'][0]['load_state']);
        $reader = new Op09LocalDouble('reader');
        $reader->rows = $import->rows;
        $before = $reader->rows;
        $read = \op09Reconcile($reader, $expected, false);
        $this->assertSame('ARCHIVE_READ_VERIFIED_NOT_ACTIVATED', $read['status']);
        $this->assertSame($expected->counts(), $read['counts']);
        $this->assertSame($before, $reader->rows);
        $this->assertSame([], array_values(array_filter($reader->operations, fn ($e) => in_array($e[0], ['insert', 'update'], true))));
    }

    public function test_nonempty_local_is_refused_even_when_existing_lot_is_verified(): void
    {
        $expected = CatalogueArchiveFixture::payload();
        $db = new Op09LocalDouble;
        $db->rows['archive_lots'][] = CatalogueArchiveFixture::verifiedLot($expected);
        $before = $db->rows;
        try {
            \op09Reconcile($db, $expected, true);
            $this->fail('Already populated local archives must not be reused.');
        } catch (RuntimeException $error) {
            $this->assertSame('EMPTY_LOCAL_ARCHIVE_REQUIRED', $error->getMessage());
        }
        $this->assertSame($before, $db->rows);
        $this->assertNotContains(['begin'], $db->operations);
    }

    public function test_wrong_target_or_role_refused_before_any_query(): void
    {
        foreach ([new ArchiveConnectionDouble, new Op09LocalDouble('reader')] as $db) {
            try {
                \op09Reconcile($db, CatalogueArchiveFixture::payload(), true);
                $this->fail('Wrong target or role must be refused.');
            } catch (RuntimeException $error) {
                $this->assertSame('LOCAL_ROLE_REQUIRED', $error->getMessage());
            }
            $this->assertSame([], $db->operations);
        }
    }

    public function test_child_error_preserves_failed_parent_and_no_children(): void
    {
        $db = new Op09LocalDouble;
        $db->failInsertTable = 'archive_products';
        try {
            \op09Reconcile($db, CatalogueArchiveFixture::payload(), true);
            $this->fail('Child error must stop the load.');
        } catch (RuntimeException $error) {
            $this->assertSame('IMPORT_FAILED_NOT_ACTIVATED', $error->getMessage());
        }
        $this->assertSame('failed', $db->rows['archive_lots'][0]['load_state']);
        foreach (['archive_categories', 'archive_products', 'archive_translations'] as $table) {
            $this->assertSame([], $db->rows[$table]);
        }
    }

    public function test_uncertain_commit_not_replayed_or_reverted(): void
    {
        $db = new Op09LocalDouble;
        $db->failCommit = true;
        try {
            \op09Reconcile($db, CatalogueArchiveFixture::payload(), true);
            $this->fail('Uncertain commit must stop.');
        } catch (RuntimeException $error) {
            $this->assertSame('IMPORT_COMMIT_STATUS_TO_INSPECT', $error->getMessage());
        }
        $this->assertSame(1, count(array_filter($db->operations, fn ($e) => $e[0] === 'commit')));
        $this->assertNotContains(['rollback'], $db->operations);
        $this->assertSame('verified', $db->rows['archive_lots'][0]['load_state']);
    }

    public function test_equal_counts_do_not_hide_corrupt_product_values(): void
    {
        $expected = CatalogueArchiveFixture::payload();
        $reader = new Op09LocalDouble('reader');
        $reader->rows['archive_lots'][] = CatalogueArchiveFixture::verifiedLot($expected);
        foreach (['archive_categories', 'archive_products', 'archive_translations'] as $table) {
            $reader->rows[$table] = $expected->rows($table);
        }
        $reader->rows['archive_products'][0]['price_cents']++;
        $this->expectExceptionMessage('SQL_VALUE_MISMATCH');
        \op09Reconcile($reader, $expected, false);
    }

    public function test_default_and_invalid_helper_arguments_never_connect(): void
    {
        foreach ([[], ['--import'], ['--read', '--access=absent'], ['--unknown'], ['--import', '--import'],
            ['--read', '--import', '--receipt=absent', '--run-dir=absent', '--access=absent']] as $arguments) {
            ob_start();
            $code = \op09Main($arguments);
            $output = json_decode(ob_get_clean(), true);
            $this->assertSame($arguments === [] ? 0 : 1, $code);
            $this->assertSame($arguments === [] ? 'OP09_HELPER_PREPARED_NO_SQL' : 'STOP_INSPECT_NO_RETRY', $output['status']);
        }
    }
}

/** Test-only local adapter. Its inherited PDO factory always throws REAL_PDO_FORBIDDEN. */
class Op09LocalDouble extends ArchiveConnectionDouble
{
    public function __construct(string $role = 'import')
    {
        parent::__construct($role);
        $this->database = 'homya_local';
        $this->config['database'] = 'homya_local';
        $this->config['username'] = 'homya_local_'.$role;
        $this->config['unix_socket'] = config('homya.xampp_socket');
    }

    public function select($query, $bindings = [], $useReadPdo = true, array $fetchUsing = [])
    {
        if (preg_match('/select count\(\*\) as `aggregate` from `(archive_[a-z]+)`$/D', $query, $match)) {
            $this->operations[] = ['count', $match[1]];

            return [(object) ['aggregate' => count($this->rows[$match[1]])]];
        }
        $rows = parent::select($query, $bindings, $useReadPdo, $fetchUsing);
        foreach ($rows as $row) {
            if (isset($row->database_name)) {
                $row->database_name = 'homya_local';
            }
        }

        return $rows;
    }
}
