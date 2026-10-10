<?php

namespace App\Services;

use RuntimeException;

/** Immutable document fingerprints derived offline from the approved payload, never public data. */
final class CatalogueReadProfile
{
    public const LOT = '48113d706659964b80f1e72839baa55116f52dc192e5d85cd3fcedc2a9fc7809';

    public const PAYLOAD = '49811854d0b4a8da4e3cb7b3e66c99ed2f43ae9f3a71875c29073fa636ea37e1';

    // Filled from the generated and verified offline profile before delivery.
    public const PROFILE = 'adb862b6f9b96e602417d2a6d57160dfcd0040ba3115e956b7d638bfc8deca96';

    public const INDEX_COLUMNS = ['lot_key', 'slug', 'category_slug', 'index_position', 'reference', 'source_name', 'source_brand', 'source_condition', 'universe', 'price_cents', 'previous_price_cents', 'currency', 'index_document'];

    private function __construct(public readonly array $data) {}

    public static function hashRow(array $row): string
    {
        return hash('sha256', StrictJson::encode(StrictJson::canonical((object) $row)));
    }

    public static function hashRows(array $rows): string
    {
        return hash('sha256', StrictJson::encode(array_map(fn ($row) => StrictJson::canonical((object) $row), $rows)));
    }

    /** Pure/offline; constructing a test profile gives no authority to the runtime loader. */
    public static function fromPayload(CatalogueArchivePayload $payload): self
    {
        $lot = $payload->lotRow('2000-01-01 00:00:00.000000');
        $products = [];
        foreach ($payload->rows('archive_products') as $row) {
            $detail = $row['detail_document'];
            unset($row['detail_document']);
            $products[$row['slug']] = ['index' => self::hashRow($row), 'detail' => hash('sha256', $detail)];
        }
        $categories = $payload->rows('archive_categories');
        usort($categories, fn ($a, $b) => strcmp($a['slug'], $b['slug']));
        $translations = [];
        foreach (['en', 'de'] as $locale) {
            $rows = array_values(array_filter($payload->rows('archive_translations'), fn ($r) => $r['locale'] === $locale));
            usort($rows, fn ($a, $b) => $a['source_position'] <=> $b['source_position']);
            $translations[$locale] = self::hashRows($rows);
        }

        return new self(['version' => 1, 'lot_key' => $payload->lotKey(), 'payload_sha256' => $payload->digest(),
            'archive_sha256' => hash('sha256', $lot['archive_document']), 'proof_sha256' => hash('sha256', $payload->proof()),
            'counts' => $payload->counts(), 'categories_sha256' => self::hashRows($categories),
            'products' => $products, 'translations_sha256' => $translations]);
    }

    public static function loadApproved(): self
    {
        $parent = PrivateLocalFiles::path(base_path(), 'storage/app/private/catalogue-profiles');
        PrivateLocalFiles::requireMode($parent, 0700, true);
        $path = PrivateLocalFiles::path($parent, self::LOT.'.json');
        PrivateLocalFiles::requireMode($path, 0400);
        $text = file_get_contents($path);
        if (! is_string($text) || hash('sha256', $text) !== self::PROFILE) {
            throw new RuntimeException('PROFILE_NOT_APPROVED');
        }
        $data = StrictJson::decode($text);
        if (($data['version'] ?? null) !== 1 || ($data['lot_key'] ?? null) !== self::LOT || ($data['payload_sha256'] ?? null) !== self::PAYLOAD) {
            throw new RuntimeException('PROFILE_SCOPE_INVALID');
        }

        return new self($data);
    }
}
