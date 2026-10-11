<?php

declare(strict_types=1);

$root = dirname(__DIR__);

foreach (['storage/app/private', 'storage/logs'] as $relativePath) {
    $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (! is_dir($path) || is_link($path)) {
        fwrite(STDERR, "Required runtime directory is missing or unsafe: {$relativePath}\n");
        exit(1);
    }

    if (! chmod($path, 0700)) {
        fwrite(STDERR, "Could not secure runtime directory: {$relativePath}\n");
        exit(1);
    }

    clearstatcache(true, $path);

    if ((fileperms($path) & 0777) !== 0700) {
        fwrite(STDERR, "Runtime directory permissions are not 0700: {$relativePath}\n");
        exit(1);
    }
}
