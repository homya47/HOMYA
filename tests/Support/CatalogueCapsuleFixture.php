<?php

namespace Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class CatalogueCapsuleFixture
{
    public string $root;

    public string $original;

    public string $media;

    public function __construct()
    {
        // macOS Unix socket paths are limited to 103 bytes; its per-user temp path can exceed that.
        $temp = PHP_OS_FAMILY === 'Darwin' ? '/private/tmp' : sys_get_temp_dir();
        $this->root = $temp.'/homya-op04-fixture-'.bin2hex(random_bytes(8));
        mkdir($this->root, 0700);
        $this->root = realpath($this->root);
        $this->write('storage/app/private/.fixture', 'private');
        $this->media = 'public/media/catalogue/'.str_repeat('a', 24).'.jpg';
        $this->original = 'storage/app/catalogue/media-originals/'.str_repeat('b', 24).'.png';
        $this->write($this->media, "binary fixture\x00\xff");
        $this->write($this->original, "original fixture\x00");
        $image = ['local_url' => substr($this->media, 6), 'sha256' => hash_file('sha256', $this->root.'/'.$this->media), 'bytes' => filesize($this->root.'/'.$this->media)];
        $this->writeJson('storage/app/catalogue/index.json', ['meta' => ['complete' => true], 'products' => [['slug' => 'fixture', 'name' => 'Échantillon €', 'image' => $image]]]);
        $this->writeJson('storage/app/catalogue/inventory.json', ['source_url' => 'https://example.invalid/fixture']);
        $this->writeJson('storage/app/catalogue/products/fixture.json', ['slug' => 'fixture', 'description' => '', 'images' => [$image], 'brand' => '', 'variants' => []]);
        $this->writeJson('storage/app/catalogue/media-overrides.json', ['products' => ['fixture' => ['image' => $image, 'original_file' => substr($this->original, 22), 'original_sha256' => hash_file('sha256', $this->root.'/'.$this->original)]]]);
        foreach (['en', 'de'] as $locale) {
            $this->writeJson('storage/app/catalogue/localizations/'.$locale.'.json', ['locale' => $locale, 'strings' => (object) ['Échantillon €' => 'Fixture'], 'human_review' => false]);
        }
        $this->writeJson('output/qa-import/synthese.json', ['technical_checks_passed' => true, 'products' => 1, 'detailed_review_rows' => 1]);
        $this->writeJson('output/qa-import/anomalies-detaillees.json', [['id' => 'AN-fixture', 'evidence' => '']]);
        $this->refreshReview();
    }

    public function write(string $relative, string $bytes): void
    {
        $file = $this->root.'/'.$relative;
        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0700, true);
        }
        file_put_contents($file, $bytes);
    }

    public function writeJson(string $relative, mixed $data): void
    {
        $this->write($relative, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function refreshReview(): void
    {
        $paths = ['index.json', 'inventory.json', 'media-overrides.json', 'products/fixture.json', 'localizations/en.json', 'localizations/de.json'];
        $hashes = [];
        foreach ($paths as $path) {
            $relative = 'storage/app/catalogue/'.$path;
            $hashes[$relative] = hash_file('sha256', $this->root.'/'.$relative);
        }
        $this->writeJson('output/qa-import/empreintes-sources.json', $hashes);
    }

    public function parent(): string
    {
        return $this->root.'/storage/app/private/catalogue-archives';
    }

    public function destroy(): void
    {
        // Only our randomly named synthetic fixture; never a project/source/capsule directory.
        assert(str_starts_with(basename($this->root), 'homya-op04-fixture-'));
        $tree = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($tree as $file) {
            if ($file->isDir() && ! $file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->root);
    }
}
