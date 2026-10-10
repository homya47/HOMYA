<?php

use Illuminate\Contracts\Console\Kernel;

// Configuration only: no connection, PDO, migration or secret output.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$facts = ['php' => PHP_VERSION, 'environment' => $app->environment(),
    'source' => config('catalogue.source'), 'cached_config' => $app->configurationIsCached(),
    'session' => config('session.driver'), 'cache' => config('cache.default'),
    'queue' => config('queue.default'), 'pdo_mysql' => extension_loaded('pdo_mysql'),
    'posix' => function_exists('posix_geteuid'), 'view_writable' => is_writable(storage_path('framework/views'))];
echo json_encode($facts, JSON_THROW_ON_ERROR).PHP_EOL;
exit($facts['environment'] === 'local' && ! $facts['cached_config'] && $facts['pdo_mysql'] && $facts['posix']
    && $facts['session'] === 'file' && $facts['cache'] === 'file' && $facts['queue'] === 'sync' && $facts['view_writable'] ? 0 : 1);
