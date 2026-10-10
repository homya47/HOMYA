<?php

namespace App\Services;

use Illuminate\Database\Connection;
use RuntimeException;
use stdClass;

/** Private raw reader. No provider binding, activation, implicit connection or fallback. */
class CatalogueArchiveStore
{
    private ?CatalogueArchivePayload $payload = null;

    public function __construct(private Connection $connection, private string $lotKey)
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $lotKey)) {
            throw new RuntimeException('LOT_KEY_INVALID');
        }
        app(ArchiveDatabaseGuard::class)->validateConnectedRole($connection, 'reader');
    }

    public function snapshot(): CatalogueArchivePayload
    {
        if ($this->payload !== null) {
            return $this->payload;
        }
        $lot = $this->connection->table('archive_lots')->where('lot_key', $this->lotKey)->first();
        if ($lot === null || ($lot->load_state ?? null) !== 'verified') {
            throw new RuntimeException('LOT_UNAVAILABLE');
        }
        $children = [];
        foreach (['archive_categories', 'archive_products', 'archive_translations'] as $table) {
            $children[] = $this->connection->table($table)->where('lot_key', $this->lotKey)->get()->map(fn ($row) => (array) $row)->all();
        }
        $payload = CatalogueArchivePayload::fromStoredRows((array) $lot, ...$children);
        $payload->assertVerified((array) $lot);

        return $this->payload = $payload;
    }

    public function archive(): stdClass
    {
        return $this->snapshot()->archive();
    }

    public function product(string $slug): ?stdClass
    {
        if (! preg_match('/^[a-z0-9][a-z0-9-]{0,220}$/D', $slug)) {
            throw new RuntimeException('PRODUCT_SLUG_INVALID');
        }
        foreach ($this->snapshot()->rows('archive_products') as $row) {
            if ($row['slug'] === $slug) {
                return StrictJson::decode($row['detail_document'], false);
            }
        }

        return null;
    }

    public function dictionary(string $locale): stdClass
    {
        if (! in_array($locale, ['en', 'de'], true)) {
            throw new RuntimeException('DICTIONARY_LOCALE_INVALID');
        }
        $dict = clone $this->archive()->localization_metadata->{$locale};
        $dict->strings = new stdClass;
        foreach ($this->snapshot()->rows('archive_translations') as $row) {
            if ($row['locale'] === $locale) {
                $dict->strings->{$row['source_text']} = $row['translated_text'];
            }
        }

        return $dict;
    }
}
