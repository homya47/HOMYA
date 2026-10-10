<?php

namespace Tests\Support;

use App\Services\CatalogueArchivePayload;
use App\Services\CatalogueCapsule;
use App\Services\StrictJson;
use Closure;

final class CatalogueArchiveFixture
{
    public static function input(?Closure $change = null): array
    {
        $prefix = 'storage/app/catalogue/';
        $category = (object) ['slug' => 'category', 'name' => 'Catégorie'];
        $entry = (object) ['slug' => 'fixture', 'name' => 'Échantillon €', 'brand' => '', 'reference' => 'REF-1', 'category' => $category,
            'universe' => 'appliances', 'condition' => 'new', 'price_cents' => 12345, 'previous_price_cents' => null,
            'currency' => 'EUR', 'image' => null, 'issues' => []];
        $detail = clone $entry;
        unset($detail->image);
        $detail->description = '';
        $detail->images = [];
        $detail->specifications = [(object) ['label' => 'Dimensions', 'value' => 'À vérifier']];
        $detail->variants = [(object) ['title' => 'Choix sans prix', 'sku' => 'X']];
        $detail->unknown = (object) ['empty_object' => (object) [], 'empty_array' => [], 'one_float' => 1.0, 'missing_is_not_null' => null];
        $docs = [
            $prefix.'index.json' => (object) ['meta' => (object) ['complete' => true], 'products' => [$entry]],
            $prefix.'inventory.json' => (object) ['product_urls' => []],
            $prefix.'products/fixture.json' => $detail,
            $prefix.'media-overrides.json' => (object) ['products' => (object) ['fixture' => (object) ['reference' => 'REF-1']]],
            'output/qa-import/synthese.json' => (object) ['technical_checks_passed' => true, 'products' => 1, 'detailed_review_rows' => 1],
            'output/qa-import/anomalies-detaillees.json' => [(object) ['id' => 'AN-1', 'evidence' => '$7e']],
        ];
        foreach (['en', 'de'] as $locale) {
            $docs[$prefix.'localizations/'.$locale.'.json'] = (object) ['strings' => (object) ['Échantillon €' => $locale.' Fixture', '1' => 'Numeric string', 'inutilisé' => 'Unused'],
                'locale' => $locale, 'version' => 1, 'human_review' => false];
        }
        if ($change) {
            $change($docs);
        }
        $sourceHashes = new \stdClass;
        foreach ($docs as $path => $doc) {
            if (str_starts_with($path, $prefix)) {
                $sourceHashes->{$path} = hash('sha256', StrictJson::encode($doc));
            }
        }
        $docs['output/qa-import/empreintes-sources.json'] = $sourceHashes;
        $bytes = [];
        $files = [];
        foreach ($docs as $path => $doc) {
            $bytes[$path] = StrictJson::encode($doc);
            $files[] = ['path' => $path, 'bytes' => strlen($bytes[$path]), 'sha256' => hash('sha256', $bytes[$path])];
        }
        $manifest = CatalogueCapsule::manifest($files);

        return ['key' => hash('sha256', $manifest), 'manifest' => $manifest, 'documents' => $bytes];
    }

    public static function payload(?Closure $change = null, ?Closure $verifier = null): CatalogueArchivePayload
    {
        $i = self::input($change);

        return CatalogueArchivePayload::fromDocuments($i['key'], $i['manifest'], $i['documents'],
            $verifier ?? fn () => ['lot_key' => $i['key'], 'sql_executed' => false]);
    }

    public static function verifiedLot(CatalogueArchivePayload $payload): array
    {
        return array_replace($payload->lotRow('2026-10-08 00:00:00.000000'), ['load_state' => 'verified',
            'verification_document' => $payload->proof(), 'verified_at' => '2026-10-08 00:00:01.000000']);
    }
}
