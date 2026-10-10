<?php

namespace App\Services;

use Closure;
use Illuminate\Database\Connection;
use RuntimeException;

/** Read-only, request-local, bounded document reads; never uses the default connection. */
class SqlCatalogueSource implements CatalogueSource
{
    private ?Connection $connection = null;

    private ?array $envelope = null;

    private array $products = [];

    private array $dictionaries = [];

    public function __construct(private Closure $connect, private CatalogueReadProfile $profile) {}

    private function connection(): Connection
    {
        return $this->connection ??= ($this->connect)();
    }

    private function query(string $table)
    {
        return $this->connection()->table($table)->where('lot_key', $this->profile->data['lot_key']);
    }

    private function envelope(): array
    {
        if ($this->envelope !== null) {
            return $this->envelope;
        }
        $profile = $this->profile->data;
        $lot = $this->query('archive_lots')->select(['lot_key', 'format_version', 'archive_document', 'load_state', 'verification_document', 'registered_at', 'verified_at'])
            ->selectRaw('SHA2(manifest_document, 256) AS manifest_sha256')->first();
        if ($lot === null || $lot->lot_key !== $profile['lot_key'] || $lot->format_version !== 1 || $lot->load_state !== 'verified'
            || $lot->manifest_sha256 !== $profile['lot_key'] || ! is_string($lot->archive_document) || ! is_string($lot->verification_document)
            || hash('sha256', $lot->archive_document) !== $profile['archive_sha256'] || hash('sha256', $lot->verification_document) !== $profile['proof_sha256']) {
            throw new RuntimeException('LOT_UNAVAILABLE_OR_DIVERGENT');
        }
        foreach (['registered_at', 'verified_at'] as $key) {
            $date = is_string($lot->$key) ? \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $lot->$key, new \DateTimeZone('UTC')) : false;
            if ($date === false || $date->format('Y-m-d H:i:s.u') !== $lot->$key) {
                throw new RuntimeException('LOT_DATE_INVALID');
            }
        }
        $categories = $this->query('archive_categories')->orderBy('slug')->get()->map(fn ($r) => (array) $r)->all();
        if (count($categories) !== $profile['counts']['categories'] || CatalogueReadProfile::hashRows($categories) !== $profile['categories_sha256']) {
            throw new RuntimeException('CATEGORIES_DIVERGENT');
        }
        $rows = $this->query('archive_products')->select(CatalogueReadProfile::INDEX_COLUMNS)->orderBy('index_position')->get();
        if ($rows->count() !== count($profile['products'])) {
            throw new RuntimeException('PRODUCT_COUNTS_DIVERGENT');
        }
        $seen = [];
        foreach ($rows as $value) {
            $row = (array) $value;
            $slug = $row['slug'];
            if (isset($seen[$slug]) || ! isset($profile['products'][$slug]) || CatalogueReadProfile::hashRow($row) !== $profile['products'][$slug]['index']) {
                throw new RuntimeException('PRODUCT_INDEX_DIVERGENT');
            }
            $seen[$slug] = true;
        }
        unset($rows, $categories);
        $archive = StrictJson::decode($lot->archive_document);

        // Drop inventory/review provenance before holding request-local presentation input.
        return $this->envelope = array_intersect_key($archive, array_flip(['index', 'localization_metadata', 'media_overrides']));
    }

    public function archive(): array
    {
        return $this->envelope()['index'];
    }

    public function product(string $slug): ?array
    {
        if (! preg_match('/^[a-z0-9][a-z0-9-]{0,220}$/D', $slug)) {
            return null;
        }
        $this->envelope();
        if (! isset($this->profile->data['products'][$slug])) {
            return null;
        }
        $this->prefetchProducts([$slug]);

        return $this->products[$slug];
    }

    public function prefetchProducts(array $slugs): void
    {
        $this->envelope();
        $wanted = array_values(array_diff(array_unique($slugs), array_keys($this->products)));
        foreach (array_chunk($wanted, 64) as $chunk) {
            $rows = $this->query('archive_products')->select(['slug', 'detail_document'])->whereIn('slug', $chunk)->get();
            $loaded = [];
            foreach ($rows as $row) {
                if (! in_array($row->slug, $chunk, true) || isset($loaded[$row->slug])
                    || ! isset($this->profile->data['products'][$row->slug])
                    || hash('sha256', $row->detail_document) !== $this->profile->data['products'][$row->slug]['detail']) {
                    throw new RuntimeException('PRODUCT_DETAIL_DIVERGENT');
                }
                $loaded[$row->slug] = StrictJson::decode($row->detail_document);
            }
            if (count($loaded) !== count($chunk)) {
                throw new RuntimeException('PRODUCT_DETAIL_MISSING');
            }
            $this->products += $loaded;
        }
    }

    public function dictionary(string $locale): array
    {
        if (! in_array($locale, ['en', 'de'], true)) {
            throw new RuntimeException('DICTIONARY_LOCALE_INVALID');
        }
        if (! isset($this->dictionaries[$locale])) {
            $this->envelope();
            $rows = $this->query('archive_translations')->where('locale', $locale)->orderBy('source_position')->get()->map(fn ($r) => (array) $r)->all();
            if (CatalogueReadProfile::hashRows($rows) !== $this->profile->data['translations_sha256'][$locale]) {
                throw new RuntimeException('DICTIONARY_DIVERGENT');
            }
            $strings = [];
            foreach ($rows as $row) {
                $strings[$row['source_text']] = $row['translated_text'];
            }
            $this->dictionaries[$locale] = $strings;
        }

        return $this->dictionaries[$locale];
    }

    public function mediaOverrides(): array
    {
        return $this->envelope()['media_overrides']['products'];
    }
}
