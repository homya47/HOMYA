<?php

namespace App\Services;

use Closure;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/** Byte-preserving private catalogue snapshot. No SQL, network or catalogue projection. */
class CatalogueCapsule
{
    private string $root;

    private array $copyDirectories = [];

    public function __construct(?string $root = null, private ?Closure $freeSpace = null)
    {
        $input = $root ?? base_path();
        $resolved = realpath($input);
        if ($resolved === false || is_link($input)) {
            throw new RuntimeException('ROOT_INVALID');
        }
        $this->root = $resolved;
    }

    private const ARCHIVE = 'storage/app/catalogue/';

    private const REVIEW = 'output/qa-import/';

    public const PARENT = 'storage/app/private/catalogue-archives';

    public const REPORTS = ['synthese.json', 'anomalies-detaillees.json', 'empreintes-sources.json'];

    public static function manifest(array $files): string
    {
        $seen = [];
        foreach ($files as &$entry) {
            if (! is_array($entry) || array_keys($entry) !== ['path', 'bytes', 'sha256'] || ! is_string($entry['path'])
                || $entry['path'] === '' || str_contains($entry['path'], '\\') || str_contains($entry['path'], "\0")
                || preg_match('~(^/|//|(^|/)\.\.?(/|$))~', $entry['path'])
                || ! is_int($entry['bytes']) || $entry['bytes'] < 0
                || ! is_string($entry['sha256']) || ! preg_match('/^[a-f0-9]{64}$/D', $entry['sha256'])
                || isset($seen[$entry['path']])) {
                throw new RuntimeException('MANIFEST_ENTRY_INVALID');
            }
            $seen[$entry['path']] = true;
        }
        unset($entry);
        usort($files, fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

        return json_encode(['format_version' => 1, 'files' => $files], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
    }

    public function inspect(?Closure $progress = null): array
    {
        $result = $this->inventory($this->root, $progress);
        $parent = PrivateLocalFiles::path($this->root, self::PARENT, false);
        $this->space($parent, $result['bytes'] + strlen($result['manifest']) + 1048576);

        return $result;
    }

    private function document(string $root, string $relative): array
    {
        $path = PrivateLocalFiles::path($root, $relative);
        if (! is_file($path) || ! is_string($text = @file_get_contents($path))) {
            throw new RuntimeException('DOCUMENT_READ_FAILED');
        }
        $decoded = StrictJson::decode($text);
        if (! is_array($decoded)) {
            throw new RuntimeException('DOCUMENT_TYPE_INVALID');
        }

        return $decoded;
    }

    private function inventory(string $root, ?Closure $progress): array
    {
        // Reports are hashed before parsing and rechecked along with every source at the end.
        $reports = [];
        foreach (self::REPORTS as $name) {
            $relative = self::REVIEW.$name;
            $reports[$relative] = $this->fingerprint($root, $relative);
        }
        $review = $this->document($root, self::REVIEW.'empreintes-sources.json');
        $summary = $this->document($root, self::REVIEW.'synthese.json');
        $anomalies = $this->document($root, self::REVIEW.'anomalies-detaillees.json');
        $expected = [self::ARCHIVE.'index.json', self::ARCHIVE.'inventory.json', self::ARCHIVE.'media-overrides.json', self::ARCHIVE.'localizations/en.json', self::ARCHIVE.'localizations/de.json'];
        $sources = [];
        foreach ($review as $relative => $hash) {
            if (! is_string($relative) || ! preg_match('~^storage/app/catalogue/(index\.json|inventory\.json|media-overrides\.json|localizations/(en|de)\.json|products/[a-z0-9][a-z0-9-]{0,220}\.json)$~D', $relative)
                || ! is_string($hash) || ! preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                throw new RuntimeException('REVIEW_SOURCE_INVALID');
            }
            $entry = $this->fingerprint($root, $relative);
            if ($entry['sha256'] !== $hash) {
                throw new RuntimeException('SOURCE_REVIEW_CHANGED');
            }
            $sources[$relative] = $entry;
        }
        $index = $this->document($root, self::ARCHIVE.'index.json');
        if (($index['meta']['complete'] ?? false) !== true || ! is_array($index['products'] ?? null) || ! $index['products']) {
            throw new RuntimeException('INDEX_INCOMPLETE');
        }
        $references = [];
        $slugs = [];
        $galleryCount = 0;
        foreach ($index['products'] as $product) {
            $slug = $product['slug'] ?? null;
            if (! is_string($slug) || ! preg_match('/^[a-z0-9][a-z0-9-]{0,220}$/D', $slug) || isset($slugs[$slug])) {
                throw new RuntimeException('PRODUCT_SLUG_INVALID');
            }
            $slugs[$slug] = true;
            $relative = self::ARCHIVE.'products/'.$slug.'.json';
            $expected[] = $relative;
            $detail = $this->document($root, $relative);
            if (($detail['slug'] ?? null) !== $slug) {
                throw new RuntimeException('PRODUCT_IDENTITY_INVALID');
            }
            $this->images($product, $references);
            $this->images($detail, $references);
            $galleryCount += count($detail['images'] ?? []);
        }
        sort($expected, SORT_STRING);
        $actual = array_keys($sources);
        sort($actual, SORT_STRING);
        if ($actual !== $expected || ($summary['technical_checks_passed'] ?? false) !== true
            || ($summary['products'] ?? null) !== count($slugs)
            || ($summary['detailed_review_rows'] ?? null) !== count($anomalies)) {
            throw new RuntimeException('REVIEW_SCOPE_MISMATCH');
        }
        // Validate all documents, including inventory and translation dictionaries, unchanged.
        foreach ($sources as $relative => $entry) {
            $this->document($root, $relative);
        }
        $overrides = $this->document($root, self::ARCHIVE.'media-overrides.json');
        foreach ($overrides['products'] ?? [] as $slug => $override) {
            if (! isset($slugs[$slug]) || ! is_array($override)) {
                throw new RuntimeException('OVERLAY_IDENTITY_INVALID');
            }
            $this->images($override, $references);
            if (isset($override['original_file'])) {
                if (! is_string($override['original_file']) || ! preg_match('~^media-originals/[a-f0-9]{24}\.(jpg|jpeg|png|webp|avif|gif)$~D', $override['original_file'])) {
                    throw new RuntimeException('ORIGINAL_PATH_INVALID');
                }
                $this->reference(self::ARCHIVE.$override['original_file'], $override['original_sha256'] ?? null, null, $references);
            }
        }
        $files = $sources + $reports;
        $count = 0;
        foreach ($references as $relative => $claim) {
            $entry = $this->fingerprint($root, $relative);
            if ($entry['sha256'] !== $claim['sha256'] || ($claim['bytes'] !== null && $entry['bytes'] !== $claim['bytes'])) {
                throw new RuntimeException('MEDIA_CLAIM_MISMATCH');
            }
            $files[$relative] = $entry;
            if (++$count % 2000 === 0 && $progress) {
                $progress('media', $count);
            }
        }
        foreach ($sources + $reports as $relative => $entry) {
            if ($this->fingerprint($root, $relative) !== $entry) {
                throw new RuntimeException('DOCUMENT_CHANGED_DURING_INSPECTION');
            }
        }
        $entries = array_values($files);
        $manifest = self::manifest($entries);

        return ['files' => $entries, 'manifest' => $manifest, 'lot_key' => hash('sha256', $manifest),
            'file_count' => count($entries), 'bytes' => array_sum(array_column($entries, 'bytes')),
            'source_files' => count($sources), 'media_files' => count($references), 'review_reports' => count($reports),
            'products' => count($slugs), 'review_rows' => count($anomalies), 'gallery_associations' => $galleryCount];
    }

    private function images(array $node, array &$references): void
    {
        if (array_key_exists('local_url', $node) && $node['local_url'] !== null) {
            if (! is_string($node['local_url']) || ! preg_match('~^/media/catalogue/[a-f0-9]{24}\.(jpg|jpeg|png|webp|avif|gif)$~D', $node['local_url'])) {
                throw new RuntimeException('MEDIA_PATH_INVALID');
            }
            $this->reference('public'.$node['local_url'], $node['sha256'] ?? null, $node['bytes'] ?? null, $references);
        }
        foreach ($node as $value) {
            if (is_array($value)) {
                $this->images($value, $references);
            }
        }
    }

    private function reference(string $relative, mixed $hash, mixed $bytes, array &$references): void
    {
        if (! is_string($hash) || ! preg_match('/^[a-f0-9]{64}$/D', $hash) || ($bytes !== null && (! is_int($bytes) || $bytes < 0))) {
            throw new RuntimeException('MEDIA_METADATA_INVALID');
        }
        $claim = ['sha256' => $hash, 'bytes' => $bytes];
        if (isset($references[$relative])) {
            $prior = $references[$relative];
            if ($prior['sha256'] !== $hash || ($prior['bytes'] !== null && $bytes !== null && $prior['bytes'] !== $bytes)) {
                throw new RuntimeException('MEDIA_REFERENCES_CONFLICT');
            }
            $claim['bytes'] ??= $prior['bytes'];
        }
        $references[$relative] = $claim;
    }

    private function fingerprint(string $root, string $relative): array
    {
        $path = PrivateLocalFiles::path($root, $relative);
        $before = @stat($path);
        if (! is_file($path) || $before === false || ! is_string($hash = @hash_file('sha256', $path))) {
            throw new RuntimeException('FILE_READ_FAILED');
        }
        clearstatcache(true, $path);
        $after = @stat($path);
        if ($after === false || array_intersect_key($after, array_flip(['dev', 'ino', 'mode', 'size', 'mtime', 'ctime']))
            !== array_intersect_key($before, array_flip(['dev', 'ino', 'mode', 'size', 'mtime', 'ctime'])) || is_link($path)) {
            throw new RuntimeException('FILE_CHANGED_DURING_READ');
        }

        return ['path' => $relative, 'bytes' => $before['size'], 'sha256' => $hash];
    }

    private function space(string $path, int $required): void
    {
        while (! is_dir($path)) {
            $path = dirname($path);
        }
        $free = $this->freeSpace ? ($this->freeSpace)($path) : @disk_free_space($path);
        if (! is_numeric($free) || $free < $required) {
            throw new RuntimeException('DISK_SPACE_INSUFFICIENT');
        }
    }

    public function copy(string $expectedLotKey, ?Closure $progress = null): array
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $expectedLotKey)) {
            throw new RuntimeException('EXPECTED_LOT_KEY_REQUIRED');
        }
        $this->copyDirectories = [];
        $inspection = $this->inspect($progress);
        if ($inspection['lot_key'] !== $expectedLotKey) {
            throw new RuntimeException('SIMULATED_LOT_CHANGED');
        }
        $parent = PrivateLocalFiles::path($this->root, self::PARENT, false);
        if (! file_exists($parent)) {
            PrivateLocalFiles::makeDirectory($parent);
        }
        PrivateLocalFiles::requireMode($parent, 0700, true);
        $lockPath = $parent.'/.prepare.lock';
        if (! file_exists($lockPath)) {
            $newLock = @fopen($lockPath, 'x');
            if ($newLock !== false) {
                chmod($lockPath, 0600);
                fclose($newLock);
            }
        }
        PrivateLocalFiles::requireMode($lockPath, 0600);
        $lock = @fopen($lockPath, 'r+');
        if ($lock === false || fstat($lock)['ino'] !== lstat($lockPath)['ino'] || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('CAPSULE_PREPARATION_BUSY');
        }
        $stage = null;
        try {
            $destination = $parent.'/'.$inspection['lot_key'];
            if (file_exists($destination) || is_link($destination)) {
                throw new RuntimeException('CAPSULE_DESTINATION_EXISTS');
            }
            $stage = $parent.'/.staging-'.bin2hex(random_bytes(16));
            PrivateLocalFiles::makeDirectory($stage);
            PrivateLocalFiles::makeDirectory($stage.'/files');
            $remaining = $inspection['bytes'] + strlen($inspection['manifest']) + 1048576;
            foreach ($inspection['files'] as $i => $entry) {
                $this->space($parent, $remaining);
                $source = PrivateLocalFiles::path($this->root, $entry['path']);
                $target = $stage.'/files/'.$entry['path'];
                $this->directories($stage.'/files', dirname($entry['path']));
                $this->copyFile($source, $target);
                if ($this->fingerprint($this->root, $entry['path']) !== $entry
                    || $this->fingerprint($stage.'/files', $entry['path']) !== $entry) {
                    throw new RuntimeException('COPY_BYTES_MISMATCH');
                }
                if (! chmod($target, 0400)) {
                    throw new RuntimeException('COPY_MODE_FAILED');
                }
                $remaining -= $entry['bytes'];
                if ($progress && ($i + 1) % 2000 === 0) {
                    $progress('copy', $i + 1);
                }
            }
            // All original files and all copies re-read after copying, before promotion.
            foreach ($inspection['files'] as $entry) {
                if ($this->fingerprint($this->root, $entry['path']) !== $entry
                    || $this->fingerprint($stage.'/files', $entry['path']) !== $entry) {
                    throw new RuntimeException('FINAL_BYTES_MISMATCH');
                }
            }
            $this->writeNew($stage.'/manifest.json', $inspection['manifest'], 0400);
            if ($progress) {
                $progress('verification', $inspection['file_count']);
            }
            $this->verifyDirectory($stage, $inspection['lot_key'], $progress);
            // Originals may change while the copied inventory is being verified.
            foreach ($inspection['files'] as $entry) {
                if ($this->fingerprint($this->root, $entry['path']) !== $entry) {
                    throw new RuntimeException('SOURCE_CHANGED_BEFORE_PROMOTION');
                }
            }
            if (file_exists($destination) || is_link($destination) || ! @rename($stage, $destination)) {
                throw new RuntimeException('CAPSULE_PROMOTION_FAILED');
            }
            $stage = null;

            return $this->summary($inspection, 'CAPSULE_COPIED_AND_VERIFIED') + ['path' => $destination];
        } catch (Throwable $e) {
            if ($stage !== null && is_dir($stage)) {
                // Outside the capsule identity; never promote or clean up an incomplete staging.
                $failure = json_encode(['status' => 'INCOMPLETE', 'staging' => basename($stage), 'sql_executed' => false], JSON_THROW_ON_ERROR);
                $this->writeNew($parent.'/.failure-'.substr(basename($stage), 9).'.json', $failure, 0600);
            }
            throw $e;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function directories(string $root, string $relative): void
    {
        $path = $root;
        foreach (explode('/', $relative) as $part) {
            $path .= '/'.$part;
            if (! file_exists($path)) {
                PrivateLocalFiles::makeDirectory($path);
            }
            PrivateLocalFiles::path($root, substr($path, strlen($root) + 1));
            if (! isset($this->copyDirectories[$path])) {
                PrivateLocalFiles::requireMode($path, 0700, true);
                $this->copyDirectories[$path] = true;
            }
        }
    }

    private function copyFile(string $source, string $target): void
    {
        $input = @fopen($source, 'rb');
        $output = @fopen($target, 'xb');
        try {
            if ($input === false || $output === false || ! chmod($target, 0600)
                || stream_copy_to_stream($input, $output) === false || ! fflush($output)) {
                throw new RuntimeException('COPY_IO_FAILED');
            }
        } finally {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
        }
    }

    private function writeNew(string $path, string $bytes, int $mode): void
    {
        $file = @fopen($path, 'xb');
        if ($file === false) {
            throw new RuntimeException('PRIVATE_WRITE_FAILED');
        }
        try {
            if (! chmod($path, 0600) || fwrite($file, $bytes) !== strlen($bytes) || ! fflush($file) || ! fsync($file) || ! chmod($path, $mode)) {
                throw new RuntimeException('PRIVATE_WRITE_FAILED');
            }
        } finally {
            fclose($file);
        }
    }

    public function verify(string $lotKey, ?Closure $progress = null): array
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $lotKey)) {
            throw new RuntimeException('LOT_KEY_INVALID');
        }
        $directory = PrivateLocalFiles::path($this->root, self::PARENT.'/'.$lotKey);

        return $this->verifyDirectory($directory, $lotKey, $progress);
    }

    /** Closed JSON set only, after full capsule verification; each read is checked again. */
    public function documents(string $lotKey, ?Closure $progress = null): array
    {
        $verified = $this->verify($lotKey, $progress);
        $directory = $verified['path'];
        $manifest = file_get_contents(PrivateLocalFiles::path($directory, 'manifest.json'));
        if (! is_string($manifest) || hash('sha256', $manifest) !== $lotKey) {
            throw new RuntimeException('MANIFEST_HASH_INVALID');
        }
        $files = StrictJson::decode($manifest)['files'];
        $documents = [];
        foreach ($files as $entry) {
            $relative = $entry['path'];
            if (! str_ends_with($relative, '.json')
                || (! str_starts_with($relative, self::ARCHIVE) && ! str_starts_with($relative, self::REVIEW))) {
                continue;
            }
            $path = PrivateLocalFiles::path($directory.'/files', $relative);
            $bytes = @file_get_contents($path);
            if (! is_string($bytes) || strlen($bytes) !== $entry['bytes'] || hash('sha256', $bytes) !== $entry['sha256']
                || $this->fingerprint($directory.'/files', $relative) !== $entry) {
                throw new RuntimeException('CAPSULE_DOCUMENT_CHANGED');
            }
            StrictJson::decode($bytes, false);
            $documents[$relative] = $bytes;
        }

        return ['manifest' => $manifest, 'documents' => $documents, 'inspection' => $verified];
    }

    private function verifyDirectory(string $directory, string $lotKey, ?Closure $progress): array
    {
        PrivateLocalFiles::requireMode($directory, 0700, true);
        $manifestPath = PrivateLocalFiles::path($directory, 'manifest.json');
        PrivateLocalFiles::requireMode($manifestPath, 0400);
        $manifest = @file_get_contents($manifestPath);
        if (! is_string($manifest) || hash('sha256', $manifest) !== $lotKey) {
            throw new RuntimeException('MANIFEST_HASH_INVALID');
        }
        $document = StrictJson::decode($manifest);
        if (! is_array($document) || array_keys($document) !== ['format_version', 'files']
            || $document['format_version'] !== 1 || ! is_array($document['files']) || ! array_is_list($document['files'])
            || self::manifest($document['files']) !== $manifest) {
            throw new RuntimeException('MANIFEST_FORMAT_INVALID');
        }
        $expected = ['manifest.json' => true];
        $expectedDirectories = ['files' => true];
        foreach ($document['files'] as $entry) {
            $expected['files/'.$entry['path']] = true;
            $parent = dirname('files/'.$entry['path']);
            while ($parent !== '.') {
                $expectedDirectories[$parent] = true;
                $parent = dirname($parent);
            }
        }
        $tree = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        $found = [];
        foreach ($tree as $file) {
            if ($file->isLink()) {
                throw new RuntimeException('CAPSULE_SYMLINK');
            }
            if ($file->isDir()) {
                $relative = substr($file->getPathname(), strlen($directory) + 1);
                if (! isset($expectedDirectories[$relative])) {
                    throw new RuntimeException('CAPSULE_EXTRA_DIRECTORY');
                }
                PrivateLocalFiles::requireMode($file->getPathname(), 0700, true);

                continue;
            }
            $relative = substr($file->getPathname(), strlen($directory) + 1);
            if (! $file->isFile() || ! isset($expected[$relative]) || ($file->getPerms() & 0777) !== 0400 || $file->getOwner() !== posix_geteuid()) {
                throw new RuntimeException('CAPSULE_TREE_INVALID');
            }
            $found[$relative] = true;
        }
        ksort($found, SORT_STRING);
        ksort($expected, SORT_STRING);
        if ($found !== $expected) {
            throw new RuntimeException('CAPSULE_INCOMPLETE');
        }
        // Reconstruct the closed inventory from the copies: detects forged/additional manifest entries.
        $inspection = $this->inventory($directory.'/files', $progress);
        if ($inspection['manifest'] !== $manifest) {
            throw new RuntimeException('CAPSULE_CONTENT_MISMATCH');
        }

        return $this->summary($inspection, 'CAPSULE_VERIFIED_NO_SQL') + ['path' => $directory];
    }

    public function summary(array $inspection, string $status = 'SIMULATION_NO_COPY_NO_SQL'): array
    {
        return ['status' => $status, 'lot_key' => $inspection['lot_key'], 'file_count' => $inspection['file_count'],
            'bytes' => $inspection['bytes'], 'manifest_bytes' => strlen($inspection['manifest']),
            'source_files' => $inspection['source_files'], 'media_files' => $inspection['media_files'], 'review_reports' => $inspection['review_reports'],
            'products' => $inspection['products'], 'review_rows' => $inspection['review_rows'], 'gallery_associations' => $inspection['gallery_associations'],
            'sql_executed' => false, 'commercial_validation' => false];
    }
}
