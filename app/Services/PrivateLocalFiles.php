<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/** Local CLI files only; refuse traversal, symlink components, foreign ownership and ACLs. */
final class PrivateLocalFiles
{
    public static function path(string $root, string $relative, bool $mustExist = true): string
    {
        if ($relative === '' || str_contains($relative, '\\') || str_contains($relative, "\0")
            || preg_match('~(^/|//|(^|/)\.\.?(/|$))~', $relative)) {
            throw new RuntimeException('PATH_INVALID');
        }
        $base = realpath($root);
        if ($base === false || is_link($root) || ! is_dir($base)) {
            throw new RuntimeException('ROOT_INVALID');
        }
        $path = $base;
        foreach (explode('/', $relative) as $part) {
            $path .= '/'.$part;
            clearstatcache(true, $path);
            if (is_link($path) || ($mustExist && ! file_exists($path))) {
                throw new RuntimeException('PATH_UNSAFE_OR_MISSING');
            }
        }

        return $path;
    }

    public static function requireMode(string $path, int $mode, bool $directory = false): void
    {
        clearstatcache(true, $path);
        $s = @lstat($path);
        if ($s === false || is_link($path) || ($directory ? ! is_dir($path) : ! is_file($path))
            || ! function_exists('posix_geteuid') || $s['uid'] !== posix_geteuid()
            || ($s['mode'] & 0777) !== $mode) {
            throw new RuntimeException('PRIVATE_FILE_INVALID');
        }
        // macOS and Linux ls both list extended ACL entries; fail closed if inspection fails.
        $command = PHP_OS_FAMILY === 'Darwin' ? ['/bin/ls', '-lde', $path] : ['/bin/ls', '-ld', $path];
        $process = new Process($command);
        $process->run();
        if (! $process->isSuccessful() || preg_match('/^\s*\d+:/m', $process->getOutput())
            || preg_match('/^[^\s]+\+/m', $process->getOutput())) {
            throw new RuntimeException('PRIVATE_ACL_UNVERIFIED');
        }
    }

    public static function makeDirectory(string $path): void
    {
        if (file_exists($path) || is_link($path) || ! @mkdir($path, 0700)) {
            throw new RuntimeException('PRIVATE_DIRECTORY_CREATE_FAILED');
        }
        self::requireMode($path, 0700, true);
    }
}
