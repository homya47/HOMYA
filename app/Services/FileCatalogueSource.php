<?php

namespace App\Services;

use JsonException;

class FileCatalogueSource implements CatalogueSource
{
    private function read(mixed $path): ?array
    {
        try {
            $data = is_string($path) && is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : null;
        } catch (JsonException) {
            $data = null;
        }

        return is_array($data) ? $data : null;
    }

    public function archive(): array
    {
        $data = $this->read(config('catalogue.archive_path'));

        return isset($data['meta'], $data['products']) && is_array($data['meta']) && is_array($data['products'])
            ? $data : ['meta' => ['complete' => false, 'collected_total' => 0], 'products' => []];
    }

    public function product(string $slug): ?array
    {
        if (! preg_match('/^[a-z0-9][a-z0-9-]{0,220}$/D', $slug)) {
            return null;
        }
        $data = $this->read(config('catalogue.products_path').'/'.$slug.'.json');

        return ($data['slug'] ?? null) === $slug ? $data : null;
    }

    public function dictionary(string $locale): array
    {
        if (! in_array($locale, ['en', 'de'], true)) {
            return [];
        }
        $data = $this->read(config('catalogue.localizations_path').'/'.$locale.'.json');

        return ($data['version'] ?? null) === 1 && is_array($data['strings'] ?? null) ? $data['strings'] : [];
    }

    public function mediaOverrides(): array
    {
        $data = $this->read(config('catalogue.media_overrides_path'));

        return ($data['version'] ?? null) === 1 && is_array($data['products'] ?? null) ? $data['products'] : [];
    }

    public function prefetchProducts(array $slugs): void {}
}
