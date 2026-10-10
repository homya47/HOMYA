<?php

namespace App\Services;

use RuntimeException;

/** Selection is server-side only and remains lazy in file mode. */
final class SelectedCatalogueSource implements CatalogueSource
{
    private ?CatalogueSource $source = null;

    private function source(): CatalogueSource
    {
        return $this->source ??= match (config('catalogue.source')) {
            'files' => new FileCatalogueSource,
            'sql' => new SqlCatalogueSource(fn () => app(LocalCatalogueReadGuard::class)->connect(), CatalogueReadProfile::loadApproved()),
            default => throw new RuntimeException('CATALOGUE_SOURCE_INVALID'),
        };
    }

    public function archive(): array
    {
        return $this->source()->archive();
    }

    public function product(string $slug): ?array
    {
        return $this->source()->product($slug);
    }

    public function dictionary(string $locale): array
    {
        return $this->source()->dictionary($locale);
    }

    public function mediaOverrides(): array
    {
        return $this->source()->mediaOverrides();
    }

    public function prefetchProducts(array $slugs): void
    {
        $this->source()->prefetchProducts($slugs);
    }
}
