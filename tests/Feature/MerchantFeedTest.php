<?php

namespace Tests\Feature;

use App\Services\Catalogue;
use App\Services\MerchantProducts;
use App\Services\Seo;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class MerchantFeedTest extends TestCase
{
    private array $product;

    private string $reviews;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'production';
        $this->product = ['slug' => 'fixture', 'name' => 'Produit & modèle', 'reference' => 'REF', 'brand' => 'Marque',
            'description' => 'Description €', 'price_cents' => 12345, 'currency' => 'EUR', 'condition' => 'neuf',
            'has_variants' => false, 'variants' => [], 'issues' => [], 'specifications' => [],
            'images' => [['local_url' => '/media/catalogue/aaaaaaaaaaaaaaaaaaaaaaaa.png', 'width' => 500, 'height' => 500]]];
        $this->reviews = tempnam(sys_get_temp_dir(), 'homya-merchant-');
        File::ensureDirectoryExists($this->reviews.'-sessions', 0700);
        file_put_contents($this->reviews, json_encode(['fixture' => ['approved' => true,
            'fingerprints' => ['fr' => MerchantProducts::fingerprint($this->product)],
            'gtin' => '4006381333931', 'title_source' => 'default', 'description_source' => 'default']]));
        config(['seo.indexing_enabled' => true, 'seo.public_url' => 'https://homya.eu',
            'app.url' => 'https://homya.eu', 'app.debug' => false,
            'session.secure' => true, 'session.encrypt' => true, 'session.http_only' => true, 'session.domain' => null, 'session.driver' => 'file', 'session.files' => $this->reviews.'-sessions',
            'merchant.enabled' => true, 'merchant.reviews_path' => $this->reviews,
            'dashboard.enabled' => false, 'commerce.enabled' => true, 'commerce.legal_ready' => true,
            'commerce.inventory_enabled' => true, 'commerce.bank.enabled' => true,
            'commerce.bank.iban' => 'FR1420041010050500013M02606', 'commerce.bank.bic' => 'PSSTFRPPXXX',
            'commerce.bank.beneficiary' => 'Fixture', 'commerce.bank.bank_name' => 'Fixture Bank',
            'database.connections.commerce.driver' => 'mariadb', 'database.connections.commerce.database' => 'fixture',
            'database.connections.commerce.username' => 'fixture_commerce']);
        $catalogue = Mockery::mock(Catalogue::class);
        $catalogue->shouldReceive('products')->andReturn(collect([$this->product]));
        $catalogue->shouldReceive('product')->with('fixture')->andReturnUsing(fn () => $this->product);
        $catalogue->shouldReceive('sourceUnavailable', 'translationUnavailable')->andReturn(false);
        $this->app->instance(Catalogue::class, $catalogue);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->reviews.'-sessions');
        unlink($this->reviews);
        parent::tearDown();
    }

    private function inventory(array $override = []): void
    {
        $connection = Mockery::mock(Connection::class);
        $query = Mockery::mock(Builder::class);
        DB::shouldReceive('connection')->once()->with('commerce')->andReturn($connection);
        $connection->shouldReceive('table')->once()->with('commerce_inventory')->andReturn($query);
        $query->shouldReceive('select')->once()->with(['slug', 'reference', 'sale_enabled', 'approved_price_cents', 'validated_at', 'quantity', 'reserved'])->andReturnSelf();
        $query->shouldReceive('get')->once()->andReturn(collect([(object) ($override + [
            'slug' => 'fixture', 'reference' => 'REF', 'sale_enabled' => true, 'approved_price_cents' => 12345,
            'validated_at' => '2026-10-10', 'quantity' => 1000, 'reserved' => 0])]));
        // No schema, transaction, reservation expiry or inventory write is expected.
        $connection->shouldReceive('transaction', 'statement', 'insert', 'update', 'delete')->never();
    }

    private function variantReview(): array
    {
        $this->product['has_variants'] = true;
        $this->product['variants'] = [['title' => 'Blanc'], ['title' => 'Noir']];
        $review = ['approved' => true, 'fingerprints' => ['fr' => MerchantProducts::fingerprint($this->product)],
            'title_source' => 'default', 'description_source' => 'default', 'variants' => []];
        foreach (['v1' => 'Blanc', 'v2' => 'Noir'] as $id => $title) {
            $review['variants'][$id] = ['approved' => true, 'option_titles' => ['fr' => $title],
                'attributes' => ['fr' => ['color' => $title]], 'image_verified' => true,
                'images' => ['/media/catalogue/aaaaaaaaaaaaaaaaaaaaaaaa.png'], 'gtin' => $id === 'v1' ? '4006381333931' : '5012345678900',
                'title_source' => 'trained_algorithmic_media', 'description_source' => 'default'];
        }
        foreach (app(MerchantProducts::class)->variantProducts($this->product, $review, 'fr') as [$variant]) {
            $review['variants'][$variant['variant_id']]['fingerprints']['fr'] = MerchantProducts::fingerprint($variant);
        }

        return $review;
    }

    public function test_variant_feed_has_separate_ids_links_and_images_with_shared_parent_stock_and_price(): void
    {
        $review = $this->variantReview();
        $this->assertSame([], app(MerchantProducts::class)->problems($this->product, $review, 'fr'));
        File::ensureDirectoryExists($this->reviews.'-sessions', 0700);
        file_put_contents($this->reviews, json_encode(['fixture' => $review]));
        $this->inventory();
        $response = $this->get('https://homya.eu/google/merchant/fr.xml')->assertOk();
        $items = simplexml_load_string($response->getContent())->channel->item;
        $this->assertCount(2, $items);
        $ids = [];
        foreach ($items as $index => $item) {
            $data = $item->children('http://base.google.com/ns/1.0');
            $ids[] = (string) $data->id;
            $this->assertSame(MerchantProducts::id($this->product), (string) $data->item_group_id);
            $this->assertSame('123.45 EUR', (string) $data->price);
            $this->assertSame('in_stock', (string) $data->availability);
            $this->assertStringContainsString('?variant=v', (string) $data->link);
            $this->assertNotSame('', (string) $data->color);
        }
        $this->assertCount(2, array_unique($ids));
    }

    public function test_variant_images_identifiers_and_attributes_cannot_be_silently_inherited_from_parent(): void
    {
        $review = $this->variantReview();
        $merchant = app(MerchantProducts::class);
        $review['gtin'] = '4006381333931';
        unset($review['variants']['v1']['gtin']);
        $variants = $merchant->variantProducts($this->product, $review, 'fr');
        $this->assertContains('manufacturer_identifiers_required', $merchant->problems(...[$variants[0][0], $variants[0][1], 'fr']));
        $review = $this->variantReview();
        $review['variants']['v1']['image_verified'] = false;
        $variants = $merchant->variantProducts($this->product, $review, 'fr');
        $this->assertSame([], $variants[0][0]['images']);
        $this->assertContains('variant_review_required', $merchant->problems($this->product, $review, 'fr'));
        $review = $this->variantReview();
        $review['variants']['v1']['option_titles']['fr'] = 'Wrong finish';
        $this->assertSame([], $merchant->variantProducts($this->product, $review, 'fr')[0][0]['images']);
        $review = $this->variantReview();
        $review['variants']['v1']['images'] = ['https://external.example/other-model.jpg'];
        $this->assertSame([], $merchant->variantProducts($this->product, $review, 'fr')[0][0]['images']);
        $review = $this->variantReview();
        $review['variants']['v2']['attributes']['fr']['color'] = 'Blanc';
        $this->assertContains('variant_attributes_not_distinct', $merchant->problems($this->product, $review, 'fr'));
    }

    public function test_product_group_has_distinct_variant_urls_and_only_verified_images_and_offers(): void
    {
        $review = $this->variantReview();
        File::ensureDirectoryExists($this->reviews.'-sessions', 0700);
        file_put_contents($this->reviews, json_encode(['fixture' => $review]));
        $request = Request::create('https://homya.eu/fr/produit/fixture?variant=v2');
        $route = (new Route('GET', '/{locale}/produit/{slug}', []))->name('product');
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        $page = app(Seo::class)->page($request, $this->product['name'], $this->product['description'],
            ['available' => true, 'product' => $this->product, 'variant' => 'v2', 'available_stock' => 2]);
        $group = collect(json_decode($page['json'], true)['@graph'])->firstWhere('@type', 'ProductGroup');
        $this->assertNotNull($group);
        $this->assertSame(['https://schema.org/color'], $group['variesBy']);
        $this->assertCount(2, $group['hasVariant']);
        $this->assertSame('https://homya.eu/fr/produit/fixture', $page['canonical']);
        $this->assertSame('https://homya.eu/de/produit/fixture?variant=v2', $page['alternates']['de']);
        foreach ($group['hasVariant'] as $index => $variant) {
            $this->assertStringEndsWith('?variant=v'.($index + 1), $variant['url']);
            $this->assertSame($variant['url'], $variant['offers']['url']);
            $this->assertSame('123.45', $variant['offers']['price']);
            $this->assertSame(['https://homya.eu/media/catalogue/aaaaaaaaaaaaaaaaaaaaaaaa.png'], $variant['image']);
        }
        $review['variants']['v2']['image_verified'] = false;
        File::ensureDirectoryExists($this->reviews.'-sessions', 0700);
        file_put_contents($this->reviews, json_encode(['fixture' => $review]));
        $page = app(Seo::class)->page($request, $this->product['name'], $this->product['description'],
            ['available' => true, 'product' => $this->product, 'available_stock' => 2]);
        $this->assertNull(collect(json_decode($page['json'], true)['@graph'])->firstWhere('@type', 'ProductGroup'));
    }

    public function test_public_feed_exports_only_approved_matching_data_from_one_readonly_inventory_query(): void
    {
        $this->inventory();
        $response = $this->get('https://homya.eu/google/merchant/fr.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $xml = simplexml_load_string($response->getContent());
        $this->assertCount(1, $xml->channel->item);
        $item = $xml->channel->item->children('http://base.google.com/ns/1.0');
        $this->assertSame('123.45 EUR', (string) $item->price);
        $this->assertSame('new', (string) $item->condition);
        $this->assertSame('in_stock', (string) $item->availability);
        $response->assertDontSee('1000')->assertDontSee('fingerprint');
    }

    public function test_undersized_display_photos_are_excluded_from_merchant_feed(): void
    {
        $small = ['local_url' => '/media/catalogue/'.str_repeat('a', 24).'.jpg', 'width' => 499, 'height' => 600];
        $valid = ['local_url' => '/media/catalogue/'.str_repeat('b', 24).'.jpg', 'width' => 500, 'height' => 500];
        $this->product['images'] = [$small, $valid];
        $merchant = app(MerchantProducts::class);
        $review = ['approved' => true, 'fingerprints' => ['fr' => MerchantProducts::fingerprint($this->product)],
            'gtin' => '4006381333931', 'title_source' => 'default', 'description_source' => 'default'];

        $this->assertSame([], $merchant->problems($this->product, $review, 'fr'));
        $xml = simplexml_load_string($merchant->xml([[$this->product, $review, true]], 'fr'))->channel->item->children('http://base.google.com/ns/1.0');
        $this->assertSame('https://homya.eu'.$valid['local_url'], (string) $xml->image_link);
        $this->assertSame('', (string) $xml->additional_image_link);
    }

    public function test_changed_price_invalidates_the_review_instead_of_publishing_a_mismatch(): void
    {
        $this->product['price_cents'] = 12346;
        $this->inventory(['approved_price_cents' => 12346]);
        $this->get('https://homya.eu/google/merchant/fr.xml')->assertStatus(503)->assertDontSee('123.46');
    }

    public function test_stock_price_mismatch_prevents_export(): void
    {
        $this->inventory(['approved_price_cents' => 10000]);
        $this->get('https://homya.eu/google/merchant/fr.xml')->assertStatus(503);
    }

    public function test_out_of_stock_is_exported_without_revealing_inventory_counts(): void
    {
        $this->inventory(['quantity' => 12, 'reserved' => 12]);
        $response = $this->get('https://homya.eu/google/merchant/fr.xml')->assertOk();
        $item = simplexml_load_string($response->getContent())->channel->item->children('http://base.google.com/ns/1.0');
        $this->assertSame('out_of_stock', (string) $item->availability);
    }

    public function test_read_failure_never_exposes_private_errors(): void
    {
        DB::shouldReceive('connection')->once()->with('commerce')->andThrow(new RuntimeException('SECRET_SENTINEL database credentials'));
        $this->get('https://homya.eu/google/merchant/fr.xml')->assertStatus(503)
            ->assertDontSee('SECRET_SENTINEL')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_disabled_feed_never_queries_database(): void
    {
        config(['merchant.enabled' => false]);
        DB::shouldReceive('connection')->never();
        $this->get('https://homya.eu/google/merchant/fr.xml')->assertNotFound();
    }
}
