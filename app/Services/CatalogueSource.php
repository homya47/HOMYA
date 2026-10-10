<?php

namespace App\Services;

interface CatalogueSource
{
    public function archive(): array;

    public function product(string $slug): ?array;

    public function dictionary(string $locale): array;

    public function mediaOverrides(): array;

    /** Batch only the gallery fallbacks needed by the public projection. */
    public function prefetchProducts(array $slugs): void;
}
