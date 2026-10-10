<?php

namespace Tests\Unit;

use App\Services\CatalogueArchivePayload;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\CatalogueArchiveFixture;
use Tests\Support\Op06Recipe;

class Op06PreparationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_fixture_manifest_keys_match_frozen_offline_programme(): void
    {
        $frozen = json_decode(file_get_contents(dirname(__DIR__, 2).'/output/qa-import/op06-fixtures-proposees.json'), true, 64, JSON_THROW_ON_ERROR);
        foreach ($frozen['lots'] as $row) {
            $payload = Op06Recipe::payload($row['scenario']);
            $this->assertSame($row['lot_key'], $payload->lotKey());
            $this->assertSame($row['counts'], $payload->counts());
        }
        $this->expectExceptionMessage('FIXTURE_NOT_ALLOWLISTED');
        Op06Recipe::payload('unapproved');
    }

    public function test_recipe_rolls_back_an_exception_and_checks_counts_without_pdo(): void
    {
        $db = Mockery::mock(Connection::class);
        $db->shouldReceive('transactionLevel')->twice()->andReturn(0, 1);
        $db->shouldReceive('beginTransaction')->once();
        $db->shouldReceive('rollBack')->once();
        $builder = Mockery::mock(Builder::class);
        $builder->shouldReceive('count')->times(8)->andReturn(0);
        $db->shouldReceive('table')->times(8)->andReturn($builder);
        $this->expectExceptionMessage('SYNTHETIC_CALLBACK_FAILED');
        (new Op06Recipe)->rollback($db, fn () => throw new RuntimeException('SYNTHETIC_CALLBACK_FAILED'));
    }

    public function test_capacity_exceeded_is_refused_without_sql_or_file_verification(): void
    {
        $verified = false;
        $input = CatalogueArchiveFixture::input(function (array $docs): void {
            $docs['storage/app/catalogue/index.json']->meta->large_synthetic_document = str_repeat('a', 9000000);
        });
        try {
            CatalogueArchivePayload::fromDocuments($input['key'], $input['manifest'], $input['documents'], function () use (&$verified): array {
                $verified = true;

                return [];
            });
            $this->fail('Oversized payload must be rejected.');
        } catch (RuntimeException $e) {
            $this->assertSame('PAYLOAD_PACKET_BUDGET_EXCEEDED', $e->getMessage());
        }
        $this->assertFalse($verified);
    }

    public function test_instrumented_fixture_passes_guard_before_expected_second_check_failure(): void
    {
        $calls = 0;
        $payload = Op06Recipe::instrumentedPayload('failure', function () use (&$calls): void {
            if (++$calls === 2) {
                throw new RuntimeException('SYNTHETIC_PRECOMMIT_FAILURE');
            }
        });
        $payload->verifyFiles();
        $this->assertSame(1, $calls);
        $this->expectExceptionMessage('SYNTHETIC_PRECOMMIT_FAILURE');
        $payload->verifyFiles();
    }

    public function test_concurrency_fixture_runs_two_valid_checks_without_sql(): void
    {
        $calls = 0;
        $payload = Op06Recipe::instrumentedPayload('concurrency', function () use (&$calls): void {
            $calls++;
        });
        $payload->verifyFiles();
        $payload->verifyFiles();
        $this->assertSame(2, $calls);
        $this->assertSame(Op06Recipe::payload('concurrency')->lotKey(), $payload->lotKey());
    }
}
