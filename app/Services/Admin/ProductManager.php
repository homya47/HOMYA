<?php

namespace App\Services\Admin;

use App\Services\Catalogue;
use App\Services\Commerce\OrderStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProductManager
{
    private ?array $rows = null;

    public function rows(): array
    {
        if (! config('dashboard.enabled')) {
            return [];
        }

        return $this->rows ??= app(OrderStore::class)->connection()->table('commerce_admin_products')->get()->keyBy('slug')->all();
    }

    public function decorate(array $product): array
    {
        $row = $this->rows()[$product['slug']] ?? null;
        if (! $row) {
            return $product;
        }
        $content = json_decode($row->content, true, 32, JSON_THROW_ON_ERROR);
        $locale = app()->getLocale();
        $text = $content['languages'][$locale] ?? null;
        if ($text !== null) {
            $product['name'] = $text['name'];
            $product['description'] = $text['description'];
            $product['description_hidden'] = false;
        }
        $product['price_cents'] = $row->price_cents;
        $product['promotion'] = null;
        $product['brand'] = $content['brand'];
        $product['universe'] = $content['universe'];
        if (isset($content['condition'])) {
            $product['condition'] = $content['condition'];
        }
        $product['category'] = ['slug' => $content['category']['slug'], 'name' => $text['category'] ?? ($product['category']['name'] ?? $content['category']['name'])];
        $product['admin_published'] = (bool) $row->published;
        if (array_key_exists('images', $content) && $content['images'] === []) {
            $product['image'] = null;
            $product['images'] = [];
        }
        if (isset($content['image'])) {
            $product['image'] = $content['image'];
            $product['images'] = $content['images'] ?? [$content['image']];
        }

        return $product;
    }

    public function custom(): array
    {
        $items = [];
        foreach ($this->rows() as $row) {
            $content = json_decode($row->content, true, 32, JSON_THROW_ON_ERROR);
            if (! ($content['custom'] ?? false)) {
                continue;
            }
            $text = $content['languages'][app()->getLocale()] ?? null;
            // Do not invent a translation for a newly created product.
            if (! $text || ! $row->published) {
                continue;
            }
            $items[] = $this->decorate(['slug' => $row->slug, 'reference' => $row->reference, 'name' => $text['name'], 'description' => $text['description'], 'price_cents' => $row->price_cents, 'currency' => 'EUR', 'universe' => $content['universe'], 'has_variants' => false, 'variants' => [], 'issues' => [], 'condition' => null, 'specifications' => [], 'description_hidden' => false, 'image' => null, 'images' => []]);
        }

        return $items;
    }

    public function form(?string $slug): array
    {
        $db = app(OrderStore::class)->connection();
        if ($slug === null) {
            return ['slug' => '', 'reference' => '', 'revision' => 0, 'price' => '', 'quantity' => 0, 'published' => false, 'brand' => '', 'category' => '', 'languages' => ['fr' => ['name' => '', 'description' => ''], 'en' => ['name' => '', 'description' => ''], 'de' => ['name' => '', 'description' => '']]];
        }
        $row = $db->table('commerce_admin_products')->where('slug', $slug)->first();
        $inventory = $db->table('commerce_inventory')->where('slug', $slug)->first();
        abort_unless($inventory, 404);
        $languages = [];
        $originalLocale = app()->getLocale();
        $base = null;
        try {
            foreach (['fr', 'en', 'de'] as $locale) {
                app()->setLocale($locale);
                $p = app(Catalogue::class)->product($slug);
                if ($locale === 'fr') {
                    $base = $p;
                }
                if ($p) {
                    $languages[$locale] = ['name' => $p['name'], 'description' => $p['description'] ?? '', 'category' => $p['category']['name']];
                }
            }
        } finally {
            app()->setLocale($originalLocale);
        }
        $content = $row ? json_decode($row->content, true, 32, JSON_THROW_ON_ERROR) : [];

        return ['slug' => $slug, 'reference' => $inventory->reference, 'revision' => $row->revision ?? 0, 'price' => number_format($inventory->approved_price_cents / 100, 2, '.', ''), 'quantity' => $inventory->quantity, 'reserved' => $inventory->reserved, 'published' => $row ? (bool) $row->published : (bool) $inventory->sale_enabled, 'brand' => $content['brand'] ?? ($base['brand'] ?? ''), 'category' => $content['category']['name'] ?? ($base['category']['name'] ?? ''), 'languages' => $content['languages'] ?? $languages, 'image' => $content['image'] ?? ($base['image'] ?? null), 'universe' => $content['universe'] ?? ($base['universe'] ?? 'other'), 'images' => $content['images'] ?? ($base['images'] ?? []), 'condition' => $content['condition'] ?? ($base['condition'] ?? null)];
    }

    public function save(?string $slug, array $data, $image = null, array $extraImages = []): string
    {
        return Cache::lock('admin-product-write', 30)->block(5, function () use ($slug, $data, $image, $extraImages) {
            $db = app(OrderStore::class)->connection();
            $new = $slug === null;
            $slug ??= 'homya-'.Str::lower((string) Str::ulid());
            $existing = $db->table('commerce_admin_products')->where('slug', $slug)->first();
            $previous = $existing ? json_decode($existing->content, true, 32, JSON_THROW_ON_ERROR) : [];
            $languages = [];
            foreach (['fr', 'en', 'de'] as $locale) {
                if (trim($data['languages'][$locale]['name'] ?? '') !== '') {
                    $languages[$locale] = ['name' => trim($data['languages'][$locale]['name']), 'description' => trim($data['languages'][$locale]['description'] ?? ''), 'category' => trim($data['languages'][$locale]['category'] ?? $data['category'])];
                }
            }
            $content = ['custom' => $previous['custom'] ?? $new, 'universe' => $previous['universe'] ?? $data['universe'], 'languages' => $languages, 'condition' => $data['condition'] ?? ($previous['condition'] ?? null), 'brand' => $data['brand'], 'category' => ['slug' => Str::slug($data['category']), 'name' => $data['category']]];
            $gallery = $previous['images'] ?? (isset($previous['image']) ? [$previous['image']] : []);
            if (! $existing && ! $new) {
                $gallery = app(Catalogue::class)->product($slug)['images'] ?? [];
            }
            $gallery = array_values(array_filter($gallery, fn ($photo, $index) => ! in_array($index, array_map('intval', $data['remove_images'] ?? []), true), ARRAY_FILTER_USE_BOTH));
            $written = [];
            $price = Catalogue::cents($data['price']);
            try {
                if ($image) {
                    $photo = $this->image($image);
                    $written[] = $photo['local_url'];
                    if ($gallery) {
                        $gallery[0] = $photo;
                    } else {
                        $gallery[] = $photo;
                    }
                }
                foreach ($extraImages as $upload) {
                    $photo = $this->image($upload);
                    $written[] = $photo['local_url'];
                    $gallery[] = $photo;
                }
                if (count($gallery) > 20) {
                    throw ValidationException::withMessages(['images' => 'La galerie peut contenir 20 photos au maximum. Retirez des photos avant d’en ajouter.']);
                }
                $content['images'] = $gallery;
                if ($gallery) {
                    $content['image'] = $gallery[0];
                }
                $db->transaction(function () use ($db, $slug, $data, $content, $price, $new) {
                    $inventory = $db->table('commerce_inventory')->where('slug', $slug)->lockForUpdate()->first();
                    $row = $db->table('commerce_admin_products')->where('slug', $slug)->lockForUpdate()->first();
                    if (($row->revision ?? 0) !== (int) $data['revision']) {
                        throw ValidationException::withMessages(['revision' => 'Ce produit a été modifié. Rechargez sa fiche avant de continuer.']);
                    }
                    if (! $new && ! $inventory) {
                        abort(404);
                    }
                    if ($data['quantity'] < ($inventory->reserved ?? 0)) {
                        throw ValidationException::withMessages(['quantity' => 'Le stock total ne peut pas être inférieur au stock réservé.']);
                    }
                    if ($new && $db->table('commerce_inventory')->where('reference', $data['reference'])->exists()) {
                        throw ValidationException::withMessages(['reference' => 'Cette référence est déjà utilisée.']);
                    }
                    $reference = $inventory->reference ?? $data['reference'];
                    $published = (bool) ($data['published'] ?? false);
                    if ($content['custom'] && $published && empty($content['condition'])) {
                        throw ValidationException::withMessages(['condition' => 'Précisez l’état réel du produit avant publication.']);
                    }
                    if ($content['custom'] && $published && ! isset($content['image'])) {
                        throw ValidationException::withMessages(['image' => 'Ajoutez une photo du produit avant publication.']);
                    }
                    $values = ['reference' => $reference, 'content' => json_encode($content, JSON_THROW_ON_ERROR), 'price_cents' => $price, 'published' => $published, 'revision' => ($row->revision ?? 0) + 1, 'updated_at' => now()];
                    if (! $row) {
                        $values['created_at'] = now();
                    }
                    $db->table('commerce_admin_products')->updateOrInsert(['slug' => $slug], $values);
                    $stock = ['reference' => $reference, 'quantity' => $data['quantity'], 'approved_price_cents' => $price, 'sale_enabled' => $published, 'validated_at' => now(), 'updated_at' => now()];
                    if (! $inventory) {
                        $stock += ['reserved' => 0, 'created_at' => now()];
                    }
                    $db->table('commerce_inventory')->updateOrInsert(['slug' => $slug], $stock);
                    app(Activity::class)->record('product_saved', $slug, $db, $slug.'|'.$values['revision']);
                });
            } catch (\Throwable $error) {
                foreach ($written as $url) {
                    File::delete(public_path(ltrim($url, '/')));
                }
                throw $error;
            }
            $this->rows = null;

            return $slug;
        });
    }

    private function image($upload): array
    {
        abort_unless(extension_loaded('gd'), 503);
        $dimensions = @getimagesize($upload->getPathname());
        if (! $dimensions || ! in_array($dimensions[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || min($dimensions[0], $dimensions[1]) < 500 || $dimensions[0] * $dimensions[1] > 16_000_000) {
            throw ValidationException::withMessages(['image' => 'Photo JPEG, PNG ou WebP, au moins 500 × 500 pixels, au plus 16 millions de pixels.']);
        }
        $decoded = @imagecreatefromstring(file_get_contents($upload->getPathname()));
        if (! $decoded) {
            throw ValidationException::withMessages(['image' => 'Cette image ne peut pas être décodée.']);
        }
        try {
            $name = Str::random(40).'.webp';
            $directory = public_path('media/admin-products');
            File::ensureDirectoryExists($directory, 0755);
            abort_if(is_link($directory), 503);
            $path = $directory.'/'.$name;
            if (! imagewebp($decoded, $path, 88)) {
                throw new \RuntimeException('IMAGE_WRITE_FAILED');
            }
            chmod($path, 0644);

            return ['local_url' => '/media/admin-products/'.$name, 'width' => $dimensions[0], 'height' => $dimensions[1]];
        } finally {
            unset($decoded);
        }
    }
}
