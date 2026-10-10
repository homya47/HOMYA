<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class Op07SnapshotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2).'/scripts/op07_snapshot.php';
    }

    public function test_canonical_rows_preserve_null_empty_binary_and_boundaries(): void
    {
        $rows = [[null], [''], ['NULL'], ["\0"], ['a', 'bc'], ['ab', 'c']];
        $hashes = array_map(fn ($row) => \op07_hash_rows([$row])['sha256'], $rows);
        $this->assertCount(6, array_unique($hashes));
        $this->assertSame('N;B0:;B1:a;', \op07_row_bytes([null, '', 'a']));
        $this->assertNotSame(\op07_hash_rows([['1'], ['2']]), \op07_hash_rows([['2'], ['1']]));
    }

    public function test_numeric_cast_is_only_for_integer_columns_and_preserves_documents(): void
    {
        $rows = [['id' => '7', 'price' => '12.3400', 'brand' => '', 'document' => '{"n":1.0}', 'date' => '2026-10-08 12:00:00.000001']];
        $columns = [['name' => 'id', 'type' => 'int'], ['name' => 'price', 'type' => 'decimal'], ['name' => 'brand', 'type' => 'varchar'], ['name' => 'document', 'type' => 'longtext'], ['name' => 'date', 'type' => 'datetime']];
        $expected = $rows;
        $expected[0]['id'] = 7;
        $this->assertSame($expected, \op07_payload_rows($rows, $columns));
    }

    public function test_out_of_range_integer_refused_instead_of_float_rounding(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('INTEGER_RANGE_INVALID');
        \op07_payload_rows([['id' => '9999999999999999999999999']], [['name' => 'id', 'type' => 'bigint']]);
    }

    public function test_missing_cli_arguments_stop_before_bootstrap_or_pdo(): void
    {
        ob_start();
        $result = \op07_snapshot_main(['op07_snapshot.php']);
        $text = ob_get_clean();
        $this->assertSame(1, $result);
        $this->assertSame(['status' => 'STOP', 'code' => 'SNAPSHOT_ARGUMENTS'], json_decode($text, true));
    }
}
