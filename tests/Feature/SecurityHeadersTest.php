<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use App\Services\CatalogueSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Support\CatalogueArchiveFixture;
use Tests\Support\CatalogueCapsuleFixture;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    private CatalogueCapsuleFixture $files;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('connectUsing')->never();
        $this->files = new CatalogueCapsuleFixture;
        foreach (CatalogueArchiveFixture::input()['documents'] as $path => $text) {
            $this->files->write($path, $text);
        }
        config(['catalogue.source' => 'files',
            'catalogue.archive_path' => $this->files->root.'/storage/app/catalogue/index.json',
            'catalogue.products_path' => $this->files->root.'/storage/app/catalogue/products',
            'catalogue.localizations_path' => $this->files->root.'/storage/app/catalogue/localizations',
            'catalogue.media_overrides_path' => $this->files->root.'/storage/app/catalogue/media-overrides.json']);
    }

    protected function tearDown(): void
    {
        $this->files->destroy();
        parent::tearDown();
    }

    private function assertSecurityHeaders(TestResponse $response): void
    {
        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_offline_suite_overrides_a_sql_source_in_the_parent_environment(): void
    {
        $this->assertSame('files', $_ENV['HOMYA_CATALOGUE_SOURCE'] ?? null);
    }

    public function test_headers_protect_catalogue_success_redirect_and_validation_errors(): void
    {
        $this->assertSecurityHeaders($this->get('/fr/catalogue')->assertOk());
        $this->assertSecurityHeaders($this->get('/')->assertRedirect('/fr'));
        $this->assertSecurityHeaders($this->get('/fr/catalogue?page=0')->assertStatus(422));
        $this->assertSecurityHeaders($this->get('/fr/produit/absent')->assertNotFound());
    }

    public function test_headers_also_protect_framework_rendered_not_found_and_health_response(): void
    {
        $this->assertSecurityHeaders($this->get('/route-inexistante')->assertNotFound());
        $this->assertSecurityHeaders($this->get('/up')->assertOk());
    }

    private function failingSource(): CatalogueSource
    {
        return new class implements CatalogueSource
        {
            public function archive(): array
            {
                throw new RuntimeException('SECRET_SENTINEL mysql://private SQL select');
            }

            public function product(string $slug): ?array
            {
                return null;
            }

            public function dictionary(string $locale): array
            {
                return [];
            }

            public function mediaOverrides(): array
            {
                return [];
            }

            public function prefetchProducts(array $slugs): void {}
        };
    }

    public function test_source_failure_is_logged_without_exception_context_or_secret_disclosure(): void
    {
        Log::shouldReceive('warning')->once()->with('HOMYA_CATALOGUE_SOURCE_UNAVAILABLE');
        $this->app->instance(CatalogueSource::class, $this->failingSource());
        $response = $this->get('/fr/catalogue')->assertStatus(503)
            ->assertDontSee('SECRET_SENTINEL')->assertDontSee('mysql://private')->assertDontSee('SQL select');
        $this->assertSecurityHeaders($response);
    }

    public function test_source_failure_is_logged_once_per_instance_and_logging_failure_is_safe(): void
    {
        Log::shouldReceive('warning')->once()->with('HOMYA_CATALOGUE_SOURCE_UNAVAILABLE')
            ->andThrow(new RuntimeException('LOG_SECRET_SENTINEL'));
        $catalogue = new Catalogue($this->failingSource());
        $this->assertEmpty($catalogue->products());
        $this->assertEmpty($catalogue->products());
        $this->assertNull($catalogue->product('fixture'));
        $this->assertTrue($catalogue->sourceUnavailable());
    }
}
