<?php

namespace Tests\Unit;

use App\Services\CatalogueArchivePayload;
use App\Services\StrictJson;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\CatalogueArchiveFixture;

class CatalogueArchivePayloadTest extends TestCase
{
    public function test_fidelity_and_complete_reconstruction_of_stored_rows(): void
    {
        $p = CatalogueArchiveFixture::payload();
        $lot = CatalogueArchiveFixture::verifiedLot($p);
        $q = CatalogueArchivePayload::fromStoredRows($lot, $p->rows('archive_categories'), $p->rows('archive_products'), $p->rows('archive_translations'));
        $q->assertVerified($lot);
        $this->assertSame($p->digest(), $q->digest());
        $this->assertSame(6, $p->counts()['translations']);
        $this->assertSame('', $p->rows('archive_products')[0]['source_brand']);
        $this->assertNull($p->rows('archive_products')[0]['previous_price_cents']);
        $doc = StrictJson::decode($p->rows('archive_products')[0]['detail_document'], false);
        $this->assertInstanceOf(\stdClass::class, $doc->unknown->empty_object);
        $this->assertSame([], $doc->unknown->empty_array);
        $this->assertSame(1.0, $doc->unknown->one_float);
        $this->assertSame('$7e', $q->archive()->review_reports->{'anomalies-detaillees.json'}[0]->evidence);
        $this->assertLessThan(16777216, $p->summary()['lot_insert_conservative_budget_bytes']);
    }

    #[DataProvider('invalid')]
    public function test_invalid_data_is_rejected_before_sql(string $field, mixed $value): void
    {
        $this->expectException(RuntimeException::class);
        CatalogueArchiveFixture::payload(function (&$docs) use ($field, $value): void {
            $docs['storage/app/catalogue/index.json']->products[0]->{$field} = $value;
            $docs['storage/app/catalogue/products/fixture.json']->{$field} = $value;
        });
    }

    public static function invalid(): array
    {
        return [['price_cents', 0], ['price_cents', 1.2], ['price_cents', '100'], ['price_cents', 2147483648],
            ['previous_price_cents', -2147483649], ['currency', 'USD'], ['universe', 'unknown'], ['brand', str_repeat('é', 32768)],
            ['reference', null], ['slug', '../fixture']];
    }

    public function test_missing_document_and_changed_source_are_rejected(): void
    {
        $i = CatalogueArchiveFixture::input();
        $i['documents']['storage/app/catalogue/products/fixture.json'] .= ' ';
        $this->expectExceptionMessage('PAYLOAD_SOURCE_MISMATCH');
        CatalogueArchivePayload::fromDocuments($i['key'], $i['manifest'], $i['documents']);
    }

    public function test_shared_field_mismatch_is_not_corrected(): void
    {
        $this->expectExceptionMessage('PAYLOAD_PROJECTION_MISMATCH');
        CatalogueArchiveFixture::payload(fn (&$d) => $d['storage/app/catalogue/products/fixture.json']->price_cents = 99);
    }

    public function test_changed_sql_column_cannot_pass_by_equal_counts(): void
    {
        $p = CatalogueArchiveFixture::payload();
        $rows = $p->rows('archive_products');
        $rows[0]['source_brand'] = 'Invented';
        $this->expectExceptionMessage('SQL_VALUE_MISMATCH');
        $p->assertMatches(CatalogueArchiveFixture::verifiedLot($p), $p->rows('archive_categories'), $rows, $p->rows('archive_translations'));
    }

    public function test_unverified_or_forged_proof_is_refused(): void
    {
        $p = CatalogueArchiveFixture::payload();
        $lot = CatalogueArchiveFixture::verifiedLot($p);
        $lot['verification_document'] = '{}';
        $this->expectExceptionMessage('LOT_NOT_VERIFIED');
        $p->assertVerified($lot);
    }

    public function test_missing_file_verifier_prevents_execution(): void
    {
        $i = CatalogueArchiveFixture::input();
        $p = CatalogueArchivePayload::fromDocuments($i['key'], $i['manifest'], $i['documents']);
        $this->expectExceptionMessage('CAPSULE_VERIFIER_REQUIRED');
        $p->verifyFiles();
    }

    public function test_budget_refuses_oversize_documents_without_splitting(): void
    {
        $this->expectExceptionMessage('PAYLOAD_PACKET_BUDGET_EXCEEDED');
        CatalogueArchiveFixture::payload(fn (&$d) => $d['storage/app/catalogue/products/fixture.json']->description = str_repeat('x', 9000000));
    }

    public function test_json_comparison_preserves_types_and_arrays_but_ignores_object_key_order(): void
    {
        foreach ([['{}', '[]'], ['1', '1.0'], ['1', '"1"'], ['{"a":null}', '{}'], ['[1,2]', '[2,1]']] as [$a, $b]) {
            $this->assertFalse(StrictJson::same(StrictJson::decode($a, false), StrictJson::decode($b, false)));
        }
        $this->assertTrue(StrictJson::same(StrictJson::decode('{"a":[],"b":{}}', false), StrictJson::decode('{"b":{},"a":[]}', false)));
        $this->expectExceptionMessage('JSON_DUPLICATE_KEY');
        StrictJson::decode('{"1":1,"\u0031":2}', false);
    }

    public function test_object_key_reordering_in_sql_does_not_change_semantic_identity(): void
    {
        $p = CatalogueArchiveFixture::payload();
        $lot = CatalogueArchiveFixture::verifiedLot($p);
        $archive = StrictJson::decode($lot['archive_document'], false);
        $lot['archive_document'] = StrictJson::encode((object) array_reverse(get_object_vars($archive), true));
        $rows = $p->rows('archive_products');
        $doc = StrictJson::decode($rows[0]['detail_document'], false);
        $rows[0]['detail_document'] = StrictJson::encode((object) array_reverse(get_object_vars($doc), true));
        $q = CatalogueArchivePayload::fromStoredRows($lot, $p->rows('archive_categories'), $rows, $p->rows('archive_translations'));
        $q->assertVerified($lot);
        $this->assertSame($p->digest(), $q->digest());
    }

    public function test_wrong_overlay_reference_is_refused(): void
    {
        $this->expectExceptionMessage('PAYLOAD_OVERLAY_REFERENCE_INVALID');
        CatalogueArchiveFixture::payload(fn (&$d) => $d['storage/app/catalogue/media-overrides.json']->products->fixture->reference = 'OTHER');
    }

    public function test_reordered_translation_positions_are_detected(): void
    {
        $p = CatalogueArchiveFixture::payload();
        $t = $p->rows('archive_translations');
        $t[0]['source_position'] = 2;
        $this->expectExceptionMessage('SQL_VALUE_MISMATCH');
        $p->assertMatches(CatalogueArchiveFixture::verifiedLot($p), $p->rows('archive_categories'), $p->rows('archive_products'), $t);
    }

    public function test_invalid_date_is_not_accepted_as_verified(): void
    {
        $p = CatalogueArchiveFixture::payload();
        $lot = CatalogueArchiveFixture::verifiedLot($p);
        $lot['verified_at'] = '2026-00-00 00:00:00.000000';
        $this->expectExceptionMessage('LOT_DATE_INVALID');
        $p->assertVerified($lot);
    }

    public function test_unexpected_lot_column_is_refused(): void
    {
        $p = CatalogueArchiveFixture::payload();
        $lot = CatalogueArchiveFixture::verifiedLot($p);
        $lot['unapproved_column'] = null;
        $this->expectExceptionMessage('SQL_LOT_COLUMNS_MISMATCH');
        $p->assertLotMatches($lot);
    }
}
