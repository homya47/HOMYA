<?php

namespace Tests\Unit;

use App\Services\ResponsiveMedia;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ResponsiveMediaTest extends TestCase
{
    private string $fixture;

    private string $original = '/media/catalogue/aaaaaaaaaaaaaaaaaaaaaaaa.webp';

    private string $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = sys_get_temp_dir().'/homya-responsive-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->fixture.'/media/catalogue-display', 0700, true);
        File::makeDirectory($this->fixture.'/media/catalogue', 0700, true);
        $bytes = file_get_contents(public_path('media/home/interieur-display.webp'));
        $this->app->usePublicPath($this->fixture);
        File::put(public_path(ltrim($this->original, '/')), $bytes);
        $hash = hash('sha256', $bytes);
        $this->variant = '/media/catalogue-display/'.$hash.'.webp';
        File::put(public_path(ltrim($this->variant, '/')), $bytes);
        File::put(public_path('media/catalogue-display/manifest.json'), json_encode(['version' => 1, 'images' => [
            $this->original => ['sha256' => $hash, 'variants' => [['url' => $this->variant, 'width' => 400]]],
        ]]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixture);
        parent::tearDown();
    }

    public function test_matching_variants_are_available_without_changing_the_original(): void
    {
        $hash = hash_file('sha256', public_path(ltrim($this->original, '/')));
        $this->assertSame($this->variant.' 400w', (new ResponsiveMedia)->srcset($this->original));
        $this->assertSame($hash, hash_file('sha256', public_path(ltrim($this->original, '/'))));
    }

    public function test_changed_source_never_reuses_variants_of_the_previous_image(): void
    {
        File::put(public_path(ltrim($this->original, '/')), 'changed');
        $this->assertSame('', (new ResponsiveMedia)->srcset($this->original));
    }

    public function test_corrupt_or_missing_variants_fall_back_to_the_original_url(): void
    {
        File::put(public_path(ltrim($this->variant, '/')), 'corrupt');
        $this->assertSame('', (new ResponsiveMedia)->srcset($this->original));
        File::delete(public_path(ltrim($this->variant, '/')));
        $this->assertSame('', (new ResponsiveMedia)->srcset($this->original));
    }

    public function test_missing_or_invalid_manifest_and_external_urls_are_ignored(): void
    {
        File::delete(public_path('media/catalogue-display/manifest.json'));
        $this->assertSame('', (new ResponsiveMedia)->srcset($this->original));
        File::put(public_path('media/catalogue-display/manifest.json'), '{invalid');
        $this->assertSame('', (new ResponsiveMedia)->srcset($this->original));
        $this->assertSame('', (new ResponsiveMedia)->srcset('https://example.com/photo.jpg'));
        $this->assertSame('', (new ResponsiveMedia)->srcset('/media/catalogue/../../private.webp'));
    }

    public function test_original_src_and_dimensions_remain_in_the_rendered_component(): void
    {
        $html = view('components.product-image', ['src' => $this->original, 'width' => 800, 'height' => 800])->render();
        $this->assertStringContainsString('src="'.$this->original.'"', $html);
        $this->assertStringContainsString('srcset="'.$this->variant.' 400w"', $html);
        $this->assertStringContainsString('width="800" height="800"', $html);
    }
}
