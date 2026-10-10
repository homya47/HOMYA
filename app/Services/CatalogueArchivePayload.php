<?php

namespace App\Services;

use Closure;
use RuntimeException;
use stdClass;

/** Validated archive rows, not a sellable catalogue. No database or publication code. */
class CatalogueArchivePayload
{
    private array $categories = [];

    private array $products = [];

    private array $translations = [];

    private array $counts = [];

    private stdClass $archive;

    private function __construct(private string $lotKey, private string $manifest, private ?Closure $fileVerifier) {}

    public static function fromCapsule(CatalogueCapsule $capsule, string $key, ?Closure $progress = null): self
    {
        $input = $capsule->documents($key, $progress);

        return self::fromDocuments($key, $input['manifest'], $input['documents'], fn () => $capsule->verify($key, $progress));
    }

    /** Pure assembly also used by fixtures. Execution requires a file verifier separately. */
    public static function fromDocuments(string $key, string $manifest, array $documents, ?Closure $fileVerifier = null): self
    {
        $self = new self($key, $manifest, $fileVerifier);
        $self->validateManifest();
        $expected = [];
        foreach (StrictJson::decode($manifest)['files'] as $entry) {
            $path = $entry['path'];
            if (str_ends_with($path, '.json') && (str_starts_with($path, 'storage/app/catalogue/') || str_starts_with($path, 'output/qa-import/'))) {
                $expected[$path] = true;
                if (! isset($documents[$path]) || ! is_string($documents[$path])
                    || strlen($documents[$path]) !== $entry['bytes'] || hash('sha256', $documents[$path]) !== $entry['sha256']) {
                    throw new RuntimeException('PAYLOAD_SOURCE_MISMATCH');
                }
            }
        }
        if (array_diff_key($documents, $expected) || array_diff_key($expected, $documents)) {
            throw new RuntimeException('PAYLOAD_SCOPE_INVALID');
        }
        $read = function (string $path) use ($documents): mixed {
            if (! array_key_exists($path, $documents)) {
                throw new RuntimeException('PAYLOAD_DOCUMENT_MISSING');
            }

            return StrictJson::decode($documents[$path], false);
        };
        $prefix = 'storage/app/catalogue/';
        $dicts = new stdClass;
        foreach (['en', 'de'] as $locale) {
            $dicts->{$locale} = self::object($read($prefix.'localizations/'.$locale.'.json'));
        }
        $reports = new stdClass;
        foreach (CatalogueCapsule::REPORTS as $name) {
            $reports->{$name} = $read('output/qa-import/'.$name);
        }
        $self->assemble((object) [
            'index' => self::object($read($prefix.'index.json')),
            'inventory' => self::object($read($prefix.'inventory.json')),
            'media_overrides' => self::object($read($prefix.'media-overrides.json')),
            'localization_metadata' => new stdClass,
            'review_reports' => $reports,
        ], $dicts, fn (string $slug) => self::object($read($prefix.'products/'.$slug.'.json')));

        return $self;
    }

    /** Rebuild expectations from SQL documents, then compare every relational projection. */
    public static function fromStoredRows(array $lot, array $categories, array $products, array $translations): self
    {
        $self = new self($lot['lot_key'] ?? '', $lot['manifest_document'] ?? '', null);
        $self->validateManifest();
        $archive = self::object(StrictJson::decode($lot['archive_document'] ?? '', false));
        $dicts = new stdClass;
        foreach (['en', 'de'] as $locale) {
            $dicts->{$locale} = clone self::object($archive->localization_metadata->{$locale} ?? null);
            $dicts->{$locale}->strings = new stdClass;
        }
        usort($translations, fn ($a, $b) => [$a['locale'], $a['source_position']] <=> [$b['locale'], $b['source_position']]);
        foreach ($translations as $row) {
            $locale = $row['locale'] ?? '';
            $source = $row['source_text'] ?? null;
            if (! in_array($locale, ['en', 'de'], true) || ! is_string($source)
                || property_exists($dicts->{$locale}->strings, $source)) {
                throw new RuntimeException('STORED_TRANSLATION_INVALID');
            }
            $dicts->{$locale}->strings->{$source} = $row['translated_text'];
        }
        $details = [];
        foreach ($products as $row) {
            if (isset($details[$row['slug']])) {
                throw new RuntimeException('STORED_PRODUCT_DUPLICATE');
            }
            $details[$row['slug']] = self::object(StrictJson::decode($row['detail_document'], false));
        }
        $self->assemble($archive, $dicts, function (string $slug) use ($details): stdClass {
            if (! isset($details[$slug])) {
                throw new RuntimeException('STORED_PRODUCT_MISSING');
            }

            return $details[$slug];
        });
        $self->assertMatches($lot, $categories, $products, $translations);

        return $self;
    }

    private function validateManifest(): void
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $this->lotKey) || hash('sha256', $this->manifest) !== $this->lotKey) {
            throw new RuntimeException('PAYLOAD_LOT_INVALID');
        }
        $manifest = StrictJson::decode($this->manifest);
        if (! is_array($manifest) || array_keys($manifest) !== ['format_version', 'files'] || $manifest['format_version'] !== 1
            || ! is_array($manifest['files']) || ! array_is_list($manifest['files'])
            || CatalogueCapsule::manifest($manifest['files']) !== $this->manifest) {
            throw new RuntimeException('PAYLOAD_MANIFEST_INVALID');
        }
    }

    private function assemble(stdClass $archive, stdClass $dicts, Closure $detail): void
    {
        $envelopeKeys = array_keys(get_object_vars($archive));
        sort($envelopeKeys, SORT_STRING);
        if ($envelopeKeys !== ['index', 'inventory', 'localization_metadata', 'media_overrides', 'review_reports']) {
            throw new RuntimeException('ARCHIVE_ENVELOPE_INVALID');
        }
        $index = self::object($archive->index);
        if (! is_array($index->products ?? null) || ! array_is_list($index->products) || $index->products === []) {
            throw new RuntimeException('PAYLOAD_INDEX_INVALID');
        }
        $this->archive = clone $archive;
        $this->archive->localization_metadata = new stdClass;
        $cats = [];
        $slugs = [];
        $specs = $variants = $gallery = $emptyBrands = 0;
        foreach ($index->products as $position => $entry) {
            $entry = self::object($entry);
            $slug = self::slug($entry->slug ?? null);
            if (isset($slugs[$slug])) {
                throw new RuntimeException('PAYLOAD_PRODUCT_DUPLICATE');
            }
            $slugs[$slug] = true;
            $document = $detail($slug);
            foreach (['slug', 'category', 'reference', 'name', 'brand', 'universe', 'condition', 'price_cents', 'currency'] as $shared) {
                if (! property_exists($entry, $shared) || ! property_exists($document, $shared)
                    || ! StrictJson::same($entry->{$shared}, $document->{$shared})) {
                    throw new RuntimeException('PAYLOAD_PROJECTION_MISMATCH');
                }
            }
            if (property_exists($entry, 'previous_price_cents') !== property_exists($document, 'previous_price_cents')
                || ! StrictJson::same($entry->previous_price_cents ?? null, $document->previous_price_cents ?? null)) {
                throw new RuntimeException('PAYLOAD_PREVIOUS_PRICE_MISMATCH');
            }
            $category = self::object($entry->category);
            $categorySlug = self::slug($category->slug ?? null);
            $categoryName = self::text($category->name ?? null);
            if (isset($cats[$categorySlug]) && $cats[$categorySlug]['source_name'] !== $categoryName) {
                throw new RuntimeException('PAYLOAD_CATEGORY_CONFLICT');
            }
            $cats[$categorySlug] = ['lot_key' => $this->lotKey, 'slug' => $categorySlug, 'source_name' => $categoryName];
            $universe = $entry->universe;
            if (! in_array($universe, ['appliances', 'firewood', 'furniture', 'heating', 'televisions'], true) || $entry->currency !== 'EUR') {
                throw new RuntimeException('PAYLOAD_DOMAIN_INVALID');
            }
            $row = ['lot_key' => $this->lotKey, 'slug' => $slug, 'category_slug' => $categorySlug,
                'index_position' => self::integer($position + 1, true),
                'reference' => self::text($entry->reference), 'source_name' => self::text($entry->name),
                'source_brand' => self::text($entry->brand), 'source_condition' => self::text($entry->condition),
                'universe' => $universe, 'price_cents' => self::integer($entry->price_cents, true),
                'previous_price_cents' => ($entry->previous_price_cents ?? null) === null ? null : self::integer($entry->previous_price_cents),
                'currency' => 'EUR', 'index_document' => StrictJson::encode($entry), 'detail_document' => StrictJson::encode($document)];
            $this->products[] = $row;
            foreach (['specifications', 'variants', 'images'] as $field) {
                if (! is_array($document->{$field} ?? null) || ! array_is_list($document->{$field})) {
                    throw new RuntimeException('PAYLOAD_DETAIL_LIST_INVALID');
                }
            }
            $specs += count($document->specifications);
            $variants += count($document->variants);
            $gallery += count($document->images);
            $emptyBrands += (int) ($entry->brand === '');
        }
        ksort($cats, SORT_STRING);
        $this->categories = array_values($cats);
        foreach (['en', 'de'] as $locale) {
            $dict = self::object($dicts->{$locale} ?? null);
            if (! ($dict->strings ?? null) instanceof stdClass || ($dict->locale ?? null) !== $locale || ($dict->version ?? null) !== 1
                || get_object_vars($dict->strings) === []) {
                throw new RuntimeException('PAYLOAD_DICTIONARY_INVALID');
            }
            $meta = clone $dict;
            unset($meta->strings);
            $this->archive->localization_metadata->{$locale} = $meta;
            $keys = [];
            $position = 0;
            foreach ($dict->strings as $source => $translated) {
                $source = self::text((string) $source);
                $key = hash('sha256', $source);
                if (isset($keys[$key])) {
                    throw new RuntimeException('PAYLOAD_TRANSLATION_COLLISION');
                }
                $keys[$key] = true;
                $this->translations[] = ['lot_key' => $this->lotKey, 'locale' => $locale, 'source_key' => $key,
                    'source_text' => $source, 'translated_text' => self::text($translated), 'source_position' => self::integer(++$position, true)];
            }
        }
        $overrides = self::object($archive->media_overrides);
        if (! ($overrides->products ?? null) instanceof stdClass) {
            throw new RuntimeException('PAYLOAD_OVERLAY_INVALID');
        }
        foreach ($overrides->products as $slug => $override) {
            $override = self::object($override);
            $product = array_values(array_filter($this->products, fn ($row) => $row['slug'] === (string) $slug));
            if (count($product) !== 1 || ($override->reference ?? null) !== $product[0]['reference']) {
                throw new RuntimeException('PAYLOAD_OVERLAY_REFERENCE_INVALID');
            }
        }
        $reports = self::object($archive->review_reports);
        $summary = self::object($reports->{'synthese.json'} ?? null);
        $anomalies = $reports->{'anomalies-detaillees.json'} ?? null;
        self::object($reports->{'empreintes-sources.json'} ?? null);
        if (! is_array($anomalies) || ! array_is_list($anomalies) || ($summary->technical_checks_passed ?? null) !== true
            || ($summary->products ?? null) !== count($this->products) || ($summary->detailed_review_rows ?? null) !== count($anomalies)) {
            throw new RuntimeException('PAYLOAD_REVIEW_INVALID');
        }
        $this->counts = ['lots' => 1, 'categories' => count($this->categories), 'products' => count($this->products),
            'translations' => count($this->translations), 'specifications' => $specs, 'variants' => $variants,
            'gallery_associations' => $gallery, 'overlays' => count(get_object_vars($overrides->products)),
            'review_rows' => count($anomalies), 'empty_brands' => $emptyBrands];
        $this->assertCapacity();
    }

    public function lotKey(): string
    {
        return $this->lotKey;
    }

    public function rows(string $table): array
    {
        return match ($table) {
            'archive_categories' => $this->categories,
            'archive_products' => $this->products,
            'archive_translations' => $this->translations,
            default => throw new RuntimeException('PAYLOAD_TABLE_INVALID'),
        };
    }

    public function counts(): array
    {
        return $this->counts;
    }

    public function archive(): stdClass
    {
        return StrictJson::decode(StrictJson::encode($this->archive), false);
    }

    public function lotRow(string $registeredAt): array
    {
        return ['lot_key' => $this->lotKey, 'format_version' => 1, 'manifest_document' => $this->manifest,
            'archive_document' => StrictJson::encode($this->archive), 'load_state' => 'prepared',
            'verification_document' => null, 'registered_at' => $registeredAt, 'verified_at' => null];
    }

    public function verifyFiles(): void
    {
        if ($this->fileVerifier === null) {
            throw new RuntimeException('CAPSULE_VERIFIER_REQUIRED');
        }
        $verified = ($this->fileVerifier)();
        if (! is_array($verified) || ($verified['lot_key'] ?? null) !== $this->lotKey || ($verified['sql_executed'] ?? null) !== false) {
            throw new RuntimeException('CAPSULE_VERIFICATION_FAILED');
        }
    }

    public function digest(): string
    {
        $products = $this->products;
        foreach ($products as &$row) {
            foreach (['index_document', 'detail_document'] as $column) {
                $row[$column] = StrictJson::decode($row[$column], false);
            }
        }
        unset($row);

        // Semantic object order is ignored; row/array order, text and types are preserved.
        return hash('sha256', StrictJson::encode(StrictJson::canonical([$this->lotKey, $this->archive, $this->categories, $products, $this->translations])));
    }

    public function proof(): string
    {
        return StrictJson::encode((object) ['lot_key' => $this->lotKey, 'counts' => (object) $this->counts,
            'payload_sha256' => $this->digest(), 'files_verified' => true]);
    }

    public function assertVerified(array $lot): void
    {
        if (($lot['load_state'] ?? null) !== 'verified' || ! is_string($lot['registered_at'] ?? null) || ! is_string($lot['verified_at'] ?? null)
            || ! preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d\.\d{6}$/D', $lot['registered_at'])
            || ! preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d\.\d{6}$/D', $lot['verified_at'])
            || ! is_string($lot['verification_document'] ?? null)
            || ! StrictJson::same(StrictJson::decode($lot['verification_document'], false), StrictJson::decode($this->proof(), false))) {
            throw new RuntimeException('LOT_NOT_VERIFIED');
        }
        foreach (['registered_at', 'verified_at'] as $column) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $lot[$column], new \DateTimeZone('UTC'));
            if ($date === false || $date->format('Y-m-d H:i:s.u') !== $lot[$column]) {
                throw new RuntimeException('LOT_DATE_INVALID');
            }
        }
    }

    public function assertMatches(array $lot, array $categories, array $products, array $translations): void
    {
        $this->assertLotMatches($lot);
        foreach (['archive_categories' => $categories, 'archive_products' => $products, 'archive_translations' => $translations] as $table => $actual) {
            $expected = $this->rows($table);
            $order = match ($table) {
                'archive_categories' => fn ($a, $b) => strcmp($a['slug'], $b['slug']),
                'archive_products' => fn ($a, $b) => $a['index_position'] <=> $b['index_position'],
                'archive_translations' => fn ($a, $b) => [$a['locale'], $a['source_position']] <=> [$b['locale'], $b['source_position']],
            };
            usort($actual, $order);
            usort($expected, $order);
            if (count($actual) !== count($expected)) {
                throw new RuntimeException('SQL_ROW_COUNT_MISMATCH');
            }
            foreach ($expected as $i => $row) {
                if (array_diff(array_keys($actual[$i]), array_keys($row)) || array_diff(array_keys($row), array_keys($actual[$i]))) {
                    throw new RuntimeException('SQL_COLUMNS_MISMATCH');
                }
                foreach ($row as $column => $value) {
                    $got = $actual[$i][$column];
                    $equal = in_array($column, ['index_document', 'detail_document'], true)
                        ? is_string($got) && StrictJson::same(StrictJson::decode($got, false), StrictJson::decode($value, false)) : $got === $value;
                    if (! $equal) {
                        throw new RuntimeException('SQL_VALUE_MISMATCH');
                    }
                }
            }
        }
    }

    public function assertLotMatches(array $lot): void
    {
        $columns = array_keys($this->lotRow('2000-01-01 00:00:00.000000'));
        if (array_diff(array_keys($lot), $columns) || array_diff($columns, array_keys($lot))) {
            throw new RuntimeException('SQL_LOT_COLUMNS_MISMATCH');
        }
        if (($lot['lot_key'] ?? null) !== $this->lotKey || ($lot['format_version'] ?? null) !== 1
            || ($lot['manifest_document'] ?? null) !== $this->manifest
            || ! StrictJson::same(StrictJson::decode($lot['archive_document'] ?? '', false), $this->archive)) {
            throw new RuntimeException('SQL_LOT_MISMATCH');
        }
    }

    public function packetBudget(array $row): int
    {
        // Conservative bound for one parametrised row, including escape/protocol headroom.
        $bytes = 65536;
        foreach ($row as $value) {
            $bytes += 2 * strlen(is_string($value) ? $value : (string) $value) + 32;
        }

        return $bytes;
    }

    private function assertCapacity(): void
    {
        foreach ([$this->lotRow('2000-01-01 00:00:00.000000'), ...$this->categories, ...$this->products, ...$this->translations] as $row) {
            if ($this->packetBudget($row) > 16777216) {
                throw new RuntimeException('PAYLOAD_PACKET_BUDGET_EXCEEDED');
            }
        }
    }

    public function summary(): array
    {
        $lot = $this->lotRow('2000-01-01 00:00:00.000000');

        return ['status' => 'IMPORT_SIMULATION_NO_SQL', 'lot_key' => $this->lotKey, 'counts' => $this->counts,
            'manifest_bytes' => strlen($this->manifest), 'archive_document_bytes' => strlen($lot['archive_document']),
            'lot_insert_conservative_budget_bytes' => $this->packetBudget($lot), 'payload_sha256' => $this->digest(),
            'sql_executed' => false, 'commercial_validation' => false];
    }

    private static function object(mixed $value): stdClass
    {
        if (! $value instanceof stdClass) {
            throw new RuntimeException('PAYLOAD_OBJECT_REQUIRED');
        }

        return $value;
    }

    private static function slug(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^[a-z0-9][a-z0-9-]{0,220}$/D', $value)) {
            throw new RuntimeException('PAYLOAD_SLUG_INVALID');
        }

        return $value;
    }

    private static function integer(mixed $value, bool $positive = false): int
    {
        if (! is_int($value) || $value < ($positive ? 1 : -2147483648) || $value > 2147483647) {
            throw new RuntimeException('PAYLOAD_INTEGER_INVALID');
        }

        return $value;
    }

    private static function text(mixed $value): string
    {
        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || strlen($value) > 65535) {
            throw new RuntimeException('PAYLOAD_TEXT_INVALID');
        }

        return $value;
    }
}
