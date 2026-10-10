<?php

// Read-only application verification: no SQL, HTTP calls, image edits or migrations.
use App\Services\Catalogue;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$archive = json_decode(file_get_contents(config('catalogue.archive_path')), true, 512, JSON_THROW_ON_ERROR);
$errors = [];
$locales = [];
$overrides = json_decode(file_get_contents(config('catalogue.media_overrides_path')), true, 512, JSON_THROW_ON_ERROR)['products'];
foreach ($overrides as $slug => $replacement) {
    $originalPath = storage_path('app/catalogue/'.$replacement['original_file']);
    $imagePath = public_path(ltrim($replacement['image']['local_url'], '/'));
    $dimensions = getimagesize($imagePath);
    if (! is_file($originalPath) || hash_file('sha256', $originalPath) !== $replacement['original_sha256']
        || hash_file('sha256', $imagePath) !== $replacement['image']['sha256']
        || $dimensions[0] < 500 || $dimensions[1] < 500 || $dimensions[0] * $dimensions[1] > 64_000_000
        || filesize($imagePath) > 16_000_000 || $replacement['visual_review'] !== 'verified'
        || $replacement['transform']['resampled'] !== false) {
        $errors[] = ['slug' => $slug, 'check' => 'reviewed_replacement_integrity'];
    }
}
$blockedImages = [];
$audit = fopen(__DIR__.'/../output/qa-catalogue/images-a-remplacer.csv', 'r');
if ($audit === false) {
    throw new RuntimeException('Image audit is required before verifying suppression.');
}
$columns = fgetcsv($audit, escape: '');
while (($row = fgetcsv($audit, escape: '')) !== false) {
    $entry = array_combine($columns, $row);
    $blockedImages[$entry['file']] = $entry['sha256'];
}
fclose($audit);
$modified = [];
foreach ($blockedImages as $url => $digest) {
    if (! preg_match('/\A\/media\/catalogue\/[a-f0-9]{24}\.(?:jpg|png|webp|avif|gif)\z/', $url)
        || ! is_file(public_path(ltrim($url, '/'))) || hash_file('sha256', public_path(ltrim($url, '/'))) !== $digest) {
        $modified[] = $url;
    }
}
if ($modified) {
    $errors[] = ['check' => 'preserved_hidden_files', 'files' => $modified];
}
foreach (['fr', 'en', 'de'] as $locale) {
    $app->setLocale($locale);
    $catalogue = new Catalogue;
    $products = $catalogue->products();
    $cards = $products->keyBy('slug');
    $checked = 0;
    $hidden = 0;
    $hiddenDescriptions = 0;
    $emptyGalleries = 0;
    $hiddenCards = 0;
    if ($products->count() !== count($archive['products'])) {
        $errors[] = ['locale' => $locale, 'check' => 'catalogue_coverage'];
    }
    foreach ($archive['products'] as $entry) {
        $product = $catalogue->product($entry['slug']);
        if (! $product) {
            $errors[] = ['locale' => $locale, 'slug' => $entry['slug'], 'check' => 'localized_detail'];

            continue;
        }
        $source = json_decode(file_get_contents(config('catalogue.products_path').'/'.$entry['slug'].'.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach (['slug', 'reference', 'price_cents', 'currency', 'condition', 'universe', 'has_variants'] as $field) {
            if ($product[$field] !== $source[$field]) {
                $errors[] = ['locale' => $locale, 'slug' => $entry['slug'], 'check' => 'preserved_field', 'field' => $field];
            }
        }
        $sourceUrls = array_column($source['images'], 'local_url');
        $expectedUrls = array_values(array_filter($sourceUrls, fn ($url) => ! isset($blockedImages[$url])));
        $affected = count($expectedUrls) !== count($sourceUrls);
        $hidden += count($sourceUrls) - count($expectedUrls);
        if (isset($overrides[$entry['slug']])) {
            $replacement = $overrides[$entry['slug']];
            if ($replacement['reference'] !== $source['reference']) {
                $errors[] = ['slug' => $entry['slug'], 'check' => 'replacement_product_reference'];
            }
            $expectedUrls = array_values(array_unique([$replacement['image']['local_url'], ...$expectedUrls]));
        }
        if (array_column($product['variants'], 'sku') !== array_column($source['variants'], 'sku') || count($product['specifications']) !== count($source['specifications'])
            || array_column($product['images'], 'local_url') !== $expectedUrls) {
            $errors[] = ['locale' => $locale, 'slug' => $entry['slug'], 'check' => 'variant_specification_gallery_coverage'];
        }
        if ($product['description_hidden'] !== $affected || ($affected && $product['description'] !== '')) {
            $errors[] = ['locale' => $locale, 'slug' => $entry['slug'], 'check' => 'associated_description_suppression'];
        }
        $card = $cards->get($entry['slug']);
        if (($card['image']['local_url'] ?? null) !== ($expectedUrls[0] ?? null)) {
            $errors[] = ['locale' => $locale, 'slug' => $entry['slug'], 'check' => 'card_primary_or_valid_fallback'];
        }
        foreach ($product['images'] as $image) {
            $dimensions = getimagesize(public_path(ltrim($image['local_url'], '/')));
            if ($dimensions[0] < 500 || $dimensions[1] < 500 || $image['width'] !== $dimensions[0] || $image['height'] !== $dimensions[1]) {
                $errors[] = ['locale' => $locale, 'slug' => $entry['slug'], 'check' => 'visible_image_dimensions'];
            }
        }
        $hiddenDescriptions += (int) $affected;
        $emptyGalleries += (int) ($expectedUrls === []);
        $hiddenCards += (int) (($card['image'] ?? null) === null);
        if (preg_match('/damdam|b-cdn\.net|source_url|captured_at|previous_price_cents/i', json_encode($product, JSON_UNESCAPED_UNICODE))) {
            $errors[] = ['locale' => $locale, 'slug' => $entry['slug'], 'check' => 'public_identity'];
        }
        $checked++;
    }
    $locales[$locale] = ['catalogue' => $products->count(), 'details' => $checked, 'translation_failure' => $catalogue->translationUnavailable(),
        'hidden_images' => $hidden, 'hidden_descriptions' => $hiddenDescriptions, 'empty_galleries' => $emptyGalleries, 'hidden_card_images' => $hiddenCards];
}
$report = ['checked_at' => gmdate('c'), 'passed' => $errors === [], 'locales' => $locales, 'errors' => $errors,
    'hidden_files_preserved' => count($blockedImages) - count($modified),
    'reviewed_replacements' => count($overrides),
    'all_products_have_photo' => array_sum(array_column($locales, 'empty_galleries')) === 0 && array_sum(array_column($locales, 'hidden_card_images')) === 0,
    'limits' => 'Application coverage, stable identities/prices/variant codes/field counts, audited image and associated description suppression, hidden file hashes and public identity. No complete visual/semantic review, Google approval or database verification.'];
file_put_contents(__DIR__.'/../output/qa-catalogue/langues-application.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents(__DIR__.'/../output/qa-catalogue/images-remplacements.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($report, JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($errors ? 1 : 0);
