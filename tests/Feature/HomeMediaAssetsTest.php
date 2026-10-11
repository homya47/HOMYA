<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HomeMediaAssetsTest extends TestCase
{
    #[DataProvider('homeImages')]
    public function test_homepage_image_is_present_and_decodable(string $filename): void
    {
        $path = public_path('media/home/'.$filename);

        $this->assertFileExists($path);
        $this->assertNotFalse(getimagesize($path), "The homepage image {$filename} must be a valid image.");
    }

    public function test_instant_transfer_logo_is_available_as_a_valid_svg_asset(): void
    {
        $path = public_path('media/payments/instant-transfer.svg');

        $this->assertFileExists($path);
        $this->assertStringContainsString('<svg ', file_get_contents($path));
        $this->assertStringContainsString('viewBox="0 0 48 48"', file_get_contents($path));
    }

    public static function homeImages(): array
    {
        return [
            'hero' => ['interieur-display.webp'],
            'heating story' => ['entrepot-display.webp'],
            'appliances story' => ['livraison-display.webp'],
            'heating department' => ['chauffage-display.webp'],
            'furniture department' => ['mobilier-display.webp'],
            'cooling department' => ['froid-display.webp'],
            'washing department' => ['lavage-display.webp'],
            'coffee department' => ['cafe.webp'],
        ];
    }
}
