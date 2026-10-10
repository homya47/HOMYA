<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use App\Services\CatalogueArchivePayload;
use App\Services\CatalogueReadProfile;
use App\Services\FileCatalogueSource;
use App\Services\SqlCatalogueSource;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\Support\CatalogueArchiveFixture;
use Tests\Support\CatalogueCapsuleFixture;
use Tests\Support\CatalogueReadConnectionDouble;
use Tests\TestCase;

class Op10NativeRecipePreparationTest extends TestCase
{
    public function test_native_default_and_missing_receipt_refuse_before_bootstrap(): void
    {
        foreach (['op10_catalogue_native.php', 'op10_candidate.php'] as $name) {
            $process = new Process(['/opt/homebrew/bin/php', base_path('scripts/'.$name)]);
            $process->mustRun();
            $this->assertStringContainsString('PREPARED', $process->getOutput());
            $this->assertSame('', $process->getErrorOutput());
        }
        $process = new Process(['/opt/homebrew/bin/php', base_path('scripts/op10_catalogue_native.php'), '--receipt=/private/tmp/no-homya-native-receipt']);
        $process->run();
        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('STOP_INSPECT_NO_RETRY', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }

    public function test_candidate_allows_only_local_get_pages_and_public_assets(): void
    {
        require_once base_path('scripts/op10_candidate.php');
        foreach (['/fr', '/en/catalogue?page=2', '/de/produit/fixture', '/build/assets/app-abc.js', '/media/catalogue/'.str_repeat('a', 24).'.jpg'] as $uri) {
            $this->assertTrue(op10CandidateAllowed('GET', '127.0.0.1', '127.0.0.1:8012', $uri, 'sql'));
        }
        foreach ([['POST', '127.0.0.1', '127.0.0.1:8012', '/fr', 'sql'], ['GET', '192.168.1.1', '127.0.0.1:8012', '/fr', 'sql'],
            ['GET', '127.0.0.1', 'site.example:8012', '/fr', 'sql'], ['GET', '127.0.0.1', '127.0.0.1:8012', '/.env', 'sql'],
            ['GET', '127.0.0.1', '127.0.0.1:8012', '/build/../.env', 'sql'], ['GET', '127.0.0.1', '127.0.0.1:8012', '/es/catalogue', 'sql'],
            ['GET', '127.0.0.1', '127.0.0.1:8012', '/fr', 'import']] as $args) {
            $this->assertFalse(op10CandidateAllowed(...$args));
        }
    }

    public function test_native_comparison_is_testable_on_synthetic_rows_without_pdo(): void
    {
        require_once base_path('scripts/op10_catalogue_native.php');
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
        $files = new CatalogueCapsuleFixture;
        try {
            $i = CatalogueArchiveFixture::input(function ($docs) {
                $docs['storage/app/catalogue/index.json']->products[0]->has_variants = false;
                $docs['storage/app/catalogue/products/fixture.json']->has_variants = false;
                $docs['storage/app/catalogue/media-overrides.json']->version = 1;
                foreach (['en', 'de'] as $locale) {
                    foreach (['Catégorie', 'Dimensions', 'À vérifier', 'Choix sans prix'] as $text) {
                        $docs['storage/app/catalogue/localizations/'.$locale.'.json']->strings->{$text} = $locale.' '.$text;
                    }
                }
            });
            foreach ($i['documents'] as $path => $text) {
                $files->write($path, $text);
            }
            config(['catalogue.archive_path' => $files->root.'/storage/app/catalogue/index.json',
                'catalogue.products_path' => $files->root.'/storage/app/catalogue/products',
                'catalogue.localizations_path' => $files->root.'/storage/app/catalogue/localizations',
                'catalogue.media_overrides_path' => $files->root.'/storage/app/catalogue/media-overrides.json']);
            $payload = CatalogueArchivePayload::fromDocuments($i['key'], $i['manifest'], $i['documents']);
            $db = new CatalogueReadConnectionDouble(['archive_lots' => [CatalogueArchiveFixture::verifiedLot($payload)],
                'archive_categories' => $payload->rows('archive_categories'), 'archive_products' => $payload->rows('archive_products'),
                'archive_translations' => $payload->rows('archive_translations')]);
            foreach (['fr', 'en', 'de'] as $locale) {
                app()->setLocale($locale);
                $report = op10Compare(new Catalogue(new FileCatalogueSource), new Catalogue(new SqlCatalogueSource(fn () => $db, CatalogueReadProfile::fromPayload($payload))), 1);
                $this->assertSame(1, $report['cards']);
                $this->assertSame(1, $report['details']);
                $this->assertSame(7, $report['filter_cases']);
            }
            $this->expectException(RuntimeException::class);
            op10Compare(new Catalogue(new FileCatalogueSource), new Catalogue(new FileCatalogueSource), 3709);
        } finally {
            $files->destroy();
        }
    }
}
