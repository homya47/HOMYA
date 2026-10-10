<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use App\Services\CatalogueArchivePayload;
use App\Services\CatalogueReadProfile;
use App\Services\CatalogueSource;
use App\Services\FileCatalogueSource;
use App\Services\LocalCatalogueReadGuard;
use App\Services\SqlCatalogueSource;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\CatalogueArchiveFixture;
use Tests\Support\CatalogueCapsuleFixture;
use Tests\Support\CatalogueReadConnectionDouble;
use Tests\TestCase;

class Op10CatalogueSourceTest extends TestCase
{
    private CatalogueCapsuleFixture $files;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
        $this->files = new CatalogueCapsuleFixture;
        config(['catalogue.source' => 'files', 'catalogue.archive_path' => $this->files->root.'/storage/app/catalogue/index.json',
            'catalogue.products_path' => $this->files->root.'/storage/app/catalogue/products',
            'catalogue.localizations_path' => $this->files->root.'/storage/app/catalogue/localizations',
            'catalogue.media_overrides_path' => $this->files->root.'/storage/app/catalogue/media-overrides.json']);
    }

    protected function tearDown(): void
    {
        $this->files->destroy();
        parent::tearDown();
    }

    private function fixture(): CatalogueArchivePayload
    {
        $input = CatalogueArchiveFixture::input(function ($docs) {
            $prefix = 'storage/app/catalogue/';
            $docs[$prefix.'index.json']->products[0]->has_variants = true;
            $docs[$prefix.'products/fixture.json']->has_variants = true;
            $docs[$prefix.'media-overrides.json']->version = 1;
            foreach (['en', 'de'] as $locale) {
                foreach (['Catégorie', 'Dimensions', 'À vérifier', 'Choix sans prix'] as $string) {
                    $docs[$prefix.'localizations/'.$locale.'.json']->strings->{$string} = $locale.' '.$string;
                }
            }
        });
        foreach ($input['documents'] as $path => $text) {
            $this->files->write($path, $text);
        }

        return CatalogueArchivePayload::fromDocuments($input['key'], $input['manifest'], $input['documents']);
    }

    private function connection(CatalogueArchivePayload $payload): CatalogueReadConnectionDouble
    {
        return new CatalogueReadConnectionDouble(['archive_lots' => [CatalogueArchiveFixture::verifiedLot($payload)],
            'archive_categories' => $payload->rows('archive_categories'), 'archive_products' => $payload->rows('archive_products'),
            'archive_translations' => $payload->rows('archive_translations')]);
    }

    public function test_file_default_never_loads_profile_credentials_or_pdo_and_cannot_be_selected_by_client(): void
    {
        $this->fixture();
        $this->app->instance(LocalCatalogueReadGuard::class, new class extends \stdClass {});
        // The private profile does not exist under this synthetic application root.
        $this->app->setBasePath($this->files->root);
        $this->app->useLangPath(__DIR__.'/../../lang');
        $response = $this->get('/fr/catalogue?HOMYA_CATALOGUE_SOURCE=sql&lot=foreign', ['X-Catalogue-Source' => 'sql'])->assertOk();
        $response->assertSee('Échantillon €')->assertDontSee('foreign');
        $this->assertSame('files', config('catalogue.source'));
    }

    public function test_sql_adapter_and_file_public_projections_match_and_reads_are_cached(): void
    {
        $payload = $this->fixture();
        $db = $this->connection($payload);
        $calls = 0;
        $sql = new SqlCatalogueSource(function () use ($db, &$calls) {
            $calls++;

            return $db;
        }, CatalogueReadProfile::fromPayload($payload));
        $this->assertSame(0, $calls);
        foreach (['fr', 'en', 'de'] as $locale) {
            app()->setLocale($locale);
            $file = new Catalogue(new FileCatalogueSource);
            $projection = new Catalogue($sql);
            $this->assertSame($file->archive(), $projection->archive());
            $this->assertSame($file->products()->all(), $projection->products()->all());
            $this->assertSame($file->product('fixture'), $projection->product('fixture'));
            foreach ([[], ['q' => 'Échantillon'], ['q' => $locale.' Fixture'], ['min' => '123,45', 'max' => '123.45'], ['sort' => 'price_desc'], ['q' => 'absent']] as $filters) {
                $this->assertSame($file->filtered($filters)->all(), $projection->filtered($filters)->all());
            }
        }
        $this->assertSame(1, $calls);
        $this->assertCount(6, $db->operations); // envelope 3, detail 1, locales 2.
        foreach ($db->operations as $op) {
            $this->assertStringStartsWith('select ', $op['query']);
        }
    }

    public static function corruptions(): array
    {
        return [
            'state' => ['archive_lots', 'load_state', 'loading', 'archive'],
            'format type' => ['archive_lots', 'format_version', '1', 'archive'],
            'lot' => ['archive_lots', 'lot_key', 'foreign', 'archive'],
            'manifest' => ['archive_lots', 'manifest_document', '{}', 'archive'],
            'proof' => ['archive_lots', 'verification_document', '{}', 'archive'],
            'envelope' => ['archive_lots', 'archive_document', '{}', 'archive'],
            'date' => ['archive_lots', 'verified_at', '2026-99-99 00:00:00.000000', 'archive'],
            'category' => ['archive_categories', 'source_name', 'Changed', 'archive'],
            'price' => ['archive_products', 'price_cents', 1, 'archive'],
            'type' => ['archive_products', 'price_cents', '12345', 'archive'],
            'position' => ['archive_products', 'index_position', 2, 'archive'],
            'index' => ['archive_products', 'index_document', '{}', 'archive'],
            'detail' => ['archive_products', 'detail_document', '{}', 'product'],
            'translation' => ['archive_translations', 'translated_text', 'Changed', 'dictionary'],
            'translation order' => ['archive_translations', 'source_position', 999, 'dictionary'],
        ];
    }

    #[DataProvider('corruptions')]
    public function test_corrupt_sql_documents_are_refused(string $table, string $field, mixed $value, string $method): void
    {
        $payload = $this->fixture();
        $db = $this->connection($payload);
        $db->rows[$table][0][$field] = $value;
        $sql = new SqlCatalogueSource(fn () => $db, CatalogueReadProfile::fromPayload($payload));
        $this->expectException(RuntimeException::class);
        match ($method) {
            'product' => $sql->product('fixture'), 'dictionary' => $sql->dictionary('en'), default => $sql->archive()
        };
    }

    public function test_missing_detail_and_children_fail_closed_while_absent_slug_is_null(): void
    {
        $payload = $this->fixture();
        $db = $this->connection($payload);
        $sql = new SqlCatalogueSource(fn () => $db, CatalogueReadProfile::fromPayload($payload));
        $this->assertNull($sql->product('../fixture'));
        $this->assertEmpty($db->operations);
        $this->assertNull($sql->product('absent'));
        $db->rows['archive_products'] = [];
        $this->expectExceptionMessage('PRODUCT_DETAIL_MISSING');
        $sql->product('fixture');
    }

    public function test_missing_table_rows_and_foreign_lot_are_refused(): void
    {
        $payload = $this->fixture();
        foreach (['archive_lots', 'archive_categories', 'archive_products', 'archive_translations'] as $table) {
            $db = $this->connection($payload);
            $db->rows[$table] = [];
            $source = new SqlCatalogueSource(fn () => $db, CatalogueReadProfile::fromPayload($payload));
            try {
                $table === 'archive_translations' ? $source->dictionary('en') : $source->archive();
                $this->fail('Missing rows must fail closed');
            } catch (RuntimeException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_healthy_missing_translation_proposes_french_explicitly_without_mixing_content(): void
    {
        $input = CatalogueArchiveFixture::input(function ($docs) {
            $docs['storage/app/catalogue/index.json']->products[0]->has_variants = true;
            $docs['storage/app/catalogue/products/fixture.json']->has_variants = true;
        });
        $payload = CatalogueArchivePayload::fromDocuments($input['key'], $input['manifest'], $input['documents']);
        $db = $this->connection($payload);
        $this->app->instance(CatalogueSource::class, new SqlCatalogueSource(fn () => $db, CatalogueReadProfile::fromPayload($payload)));
        $this->get('/en/produit/fixture')->assertStatus(503)->assertSee(__('catalogue.view_french', [], 'en'))
            ->assertSee('/fr/produit/fixture', false)->assertDontSee('Échantillon €');
        $this->get('/fr/produit/fixture')->assertOk()->assertSee('Échantillon €');
    }

    public static function locales(): array
    {
        return [['fr'], ['en'], ['de']];
    }

    #[DataProvider('locales')]
    public function test_healthy_sql_http_states_and_translation_preserve_language(string $locale): void
    {
        $payload = $this->fixture();
        $db = $this->connection($payload);
        $sql = new SqlCatalogueSource(fn () => $db, CatalogueReadProfile::fromPayload($payload));
        $this->app->instance(CatalogueSource::class, $sql);
        $this->get('/'.$locale.'/catalogue')->assertOk()->assertSee('REF-1');
        $this->get('/'.$locale.'/catalogue?q=absent')->assertOk()->assertSee(__('catalogue.empty', [], $locale));
        $this->get('/'.$locale.'/catalogue?page=0')->assertStatus(422);
        $this->get('/'.$locale.'/catalogue?page=2')->assertRedirect('/'.$locale.'/catalogue?page=1');
        $this->get('/'.$locale.'/produit/fixture')->assertOk()->assertSee('REF-1')->assertDontSee('real_pdo', false);
        $this->get('/'.$locale.'/produit/absent')->assertNotFound();
    }

    #[DataProvider('locales')]
    public function test_connection_errors_are_generic_503_without_secret_leak_or_file_fallback(string $locale): void
    {
        $payload = $this->fixture();
        config(['app.debug' => true]);
        $this->app->instance(CatalogueSource::class, new SqlCatalogueSource(fn () => throw new RuntimeException('SECRET_SENTINEL mysql://root@private-path SQL select'), CatalogueReadProfile::fromPayload($payload)));
        foreach (['/'.$locale.'/catalogue?category=unavailable', '/'.$locale.'/produit/fixture', '/'.$locale.'/produit/absent'] as $path) {
            $response = $this->get($path)->assertStatus(503)->assertSee(__('catalogue.source_unavailable', [], $locale));
            $response->assertDontSee('SECRET_SENTINEL')->assertDontSee('private-path')->assertDontSee('REF-1')->assertDontSee(__('catalogue.view_french', [], $locale));
        }
        $this->get('/'.$locale)->assertOk()->assertSee('disabled', false);
    }

    public function test_invalid_source_is_unavailable_and_route_rejected_before_source_resolution(): void
    {
        config(['catalogue.source' => 'unapproved']);
        $this->get('/es/catalogue')->assertNotFound();
        $this->get('/fr/catalogue')->assertStatus(503);
    }

    public function test_sql_selection_in_testing_is_refused_before_credentials_or_pdo(): void
    {
        config(['catalogue.source' => 'sql']);
        $this->get('/en/catalogue')->assertStatus(503)->assertSee(__('catalogue.source_unavailable', [], 'en'));
    }
}
