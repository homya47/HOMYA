<?php

namespace App\Services;

/** Presentation variants only; archive images and catalogue validation remain authoritative. */
final class ResponsiveMedia
{
    private ?array $manifest = null;

    private array $sets = [];

    public function srcset(?string $url): string
    {
        if (! is_string($url) || ! preg_match('~\A/media/catalogue/[a-f0-9]{24}\.(?:jpg|png|webp|gif)\z~', $url)) {
            return '';
        }
        if (array_key_exists($url, $this->sets)) {
            return $this->sets[$url];
        }
        $this->sets[$url] = '';
        if ($this->manifest === null) {
            $path = public_path('media/catalogue-display/manifest.json');
            $data = is_file($path) && ! is_link($path) && filesize($path) < 20_000_000
                ? json_decode(file_get_contents($path), true) : null;
            $this->manifest = ($data['version'] ?? null) === 1 && is_array($data['images'] ?? null) ? $data['images'] : [];
        }
        $entry = $this->manifest[$url] ?? null;
        $original = public_path(ltrim($url, '/'));
        if (! is_array($entry) || ! is_file($original) || is_link($original)
            || ! is_string($entry['sha256'] ?? null) || hash_file('sha256', $original) !== $entry['sha256']
            || ! is_array($entry['variants'] ?? null)) {
            return '';
        }
        $candidates = [];
        foreach ($entry['variants'] as $variant) {
            if (! is_array($variant)) {
                return '';
            }
            $variantUrl = $variant['url'] ?? '';
            if (! is_string($variantUrl) || ! preg_match('~\A/media/catalogue-display/([a-f0-9]{64})\.webp\z~', $variantUrl, $match)
                || ! is_int($variant['width'] ?? null) || $variant['width'] < 1 || $variant['width'] > 1200) {
                return '';
            }
            $path = public_path(ltrim($variantUrl, '/'));
            if (! is_file($path) || is_link($path) || hash_file('sha256', $path) !== $match[1]) {
                return '';
            }
            $candidates[] = $variantUrl.' '.$variant['width'].'w';
        }

        return $this->sets[$url] = implode(', ', $candidates);
    }
}
