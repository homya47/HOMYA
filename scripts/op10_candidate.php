<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Isolated OP-10 GET candidate only. Never changes .env or the active site. */
function op10CandidateAllowed(string $method, string $peer, string $host, string $uri, string $source): bool
{
    $path = parse_url($uri, PHP_URL_PATH);

    return $method === 'GET' && $peer === '127.0.0.1'
        && in_array($host, ['127.0.0.1:8011', '127.0.0.1:8012'], true)
        && in_array($source, ['files', 'sql'], true) && is_string($path)
        && (bool) preg_match('~^/(?:|(?:fr|en|de)(?:/catalogue|/produit/[a-z0-9][a-z0-9-]{0,220})?|(?:build|media/catalogue|fonts|images)/[a-zA-Z0-9/_\-.]+\.(?:css|js|woff2|jpg|jpeg|png|webp|avif|gif|svg)|favicon\.(?:ico|svg))$~D', $path)
        && ! str_contains($path, '..') && ! str_contains($path, '//');
}

function op10CandidateRun(): bool
{
    umask(0077);
    try {
        $source = getenv('OP10_CANDIDATE_SOURCE');
        if (! op10CandidateAllowed($_SERVER['REQUEST_METHOD'] ?? '', $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_HOST'] ?? '', $_SERVER['REQUEST_URI'] ?? '', (string) $source)) {
            http_response_code(403);

            return true;
        }
        $receipt = getenv('OP10_CANDIDATE_RECEIPT');
        $run = getenv('OP10_CANDIDATE_RUN');
        if (! is_string($receipt) || ! is_string($run) || ! preg_match('~^'.preg_quote(dirname(__DIR__).'/output/qa-import/', '~').'op10-native-run-[a-zA-Z0-9-]+$~D', $run)
            || is_link($run) || realpath($run) !== $run || ! is_dir($run) || (fileperms($run) & 0777) !== 0700
            || ! function_exists('posix_geteuid') || fileowner($run) !== posix_geteuid()
            || is_link($run.'/views') || realpath($run.'/views') !== $run.'/views'
            || (fileperms($run.'/views') & 0777) !== 0700 || fileowner($run.'/views') !== posix_geteuid()) {
            throw new RuntimeException('CANDIDATE_SCOPE_INVALID');
        }
        $process = proc_open(['/usr/bin/python3', __DIR__.'/op10_native_recipe.py', '--check-receipt', '--receipt='.$receipt],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        if (! is_resource($process)) {
            throw new RuntimeException('RECEIPT_UNAVAILABLE');
        }
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0 || $err !== '' || json_decode($out, true, flags: JSON_THROW_ON_ERROR) !== ['status' => 'NATIVE_RECEIPT_VERIFIED_NO_SQL']) {
            throw new RuntimeException('NATIVE_RECEIPT_REQUIRED');
        }
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (preg_match('~^/(?:build|media/catalogue|fonts|images)/|^/favicon\.~', $path)) {
            $asset = realpath(__DIR__.'/../public'.$path);
            if ($asset === false || ! str_starts_with($asset, realpath(__DIR__.'/../public').'/') || ! is_file($asset)) {
                http_response_code(404);

                return true;
            }

            return false; // Only allowlisted public files, after receipt validation.
        }
        require __DIR__.'/../vendor/autoload.php';
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) use ($source, $run) {
            if (! $app->isLocal() || $app->configurationIsCached() || config('catalogue.source') !== 'files') {
                throw new RuntimeException('ACTIVE_STATE_CHANGED');
            }
            config(['catalogue.source' => $source, 'app.debug' => false, 'app.url' => 'http://'.$_SERVER['HTTP_HOST'],
                'session.driver' => 'array', 'cache.default' => 'array', 'logging.default' => 'null', 'view.compiled' => $run.'/views']);
        });
        $kernel = $app->make(Kernel::class);
        $kernel->bootstrap();
        $count = 0;
        $sqlMs = 0.0;
        DB::listen(function ($event) use (&$count, &$sqlMs) {
            $count++;
            $sqlMs += $event->time;
        });
        memory_reset_peak_usage();
        $start = hrtime(true);
        $response = $kernel->handle(Request::capture());
        $telemetry = ['source' => $source, 'path' => $path, 'status' => $response->getStatusCode(),
            'seconds' => (hrtime(true) - $start) / 1e9, 'peak_bytes' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'), 'sql_queries' => $count, 'sql_ms' => $sqlMs, 'activation' => false];
        // No SQL text, bindings, DSN or credentials in telemetry. No public diagnostic route.
        $file = $run.'/http-'.bin2hex(random_bytes(12)).'.json';
        $handle = fopen($file, 'x');
        fwrite($handle, json_encode($telemetry, JSON_THROW_ON_ERROR).PHP_EOL);
        fclose($handle);
        $response->send();
        $kernel->terminate(Request::capture(), $response);

        return true;
    } catch (Throwable) {
        http_response_code(503);
        echo 'HOMYA — verification unavailable';

        return true;
    }
}

if (PHP_SAPI === 'cli-server') {
    return op10CandidateRun() ? null : false;
}
if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    echo json_encode(['status' => 'OP10_HTTP_CANDIDATE_PREPARED_NOT_STARTED', 'native_read_authorized' => false]).PHP_EOL;
}
