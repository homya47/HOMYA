<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;

final class ProductionSecurity
{
    public const HOST = 'homya.eu';

    public function localRequest(Request $request): bool
    {
        if (! app()->environment('local', 'testing')
            || ! in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)) {
            return false;
        }
        // Only an actual PHPUnit process may use synthetic public hosts in offline fixtures.
        if (app()->environment('testing') && defined('PHPUNIT_COMPOSER_INSTALL')) {
            return true;
        }
        try {
            return in_array($request->getHost(), ['localhost', '127.0.0.1', '[::1]', '::1'], true);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Named checks only: never include configuration values or credentials in reports. */
    public function checks(): array
    {
        $url = parse_url((string) config('app.url'));
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true) ?: '';
        }

        return [
            'environment_production' => app()->environment('production'),
            'debug_disabled' => config('app.debug') === false,
            'url_https_homya_eu' => is_array($url) && ($url['scheme'] ?? '') === 'https'
                && ($url['host'] ?? '') === self::HOST && ($url['port'] ?? 443) === 443
                && in_array($url['path'] ?? '', ['', '/'], true)
                && ! array_intersect(['user', 'pass', 'query', 'fragment'], array_keys($url)),
            'encryption_key_valid' => Encrypter::supported($key, config('app.cipher')),
            'session_secure' => config('session.secure') === true,
            'session_http_only' => config('session.http_only') === true,
            'session_encrypted' => config('session.encrypt') === true,
            'session_same_site' => in_array(config('session.same_site'), ['lax', 'strict'], true),
            'session_host_only' => config('session.domain') === null,
            'session_json' => config('session.serialization') === 'json',
            'session_server_side' => in_array(config('session.driver'), ['file', 'database', 'redis', 'memcached', 'dynamodb'], true),
            'private_storage_not_served' => config('filesystems.disks.local.serve') === false,
            'private_storage_permissions' => is_dir(storage_path('app/private'))
                && ! is_link(storage_path('app/private')) && (fileperms(storage_path('app/private')) & 0077) === 0,
            'logs_directory_private' => is_dir(storage_path('logs'))
                && ! is_link(storage_path('logs')) && (fileperms(storage_path('logs')) & 0077) === 0,
            'file_log_permissions' => config('logging.channels.single.permission') === 0600
                && config('logging.channels.daily.permission') === 0600
                && config('logging.channels.monthly.permission') === 0600,
            'public_storage_link_safe' => (! file_exists(public_path('storage')) && ! is_link(public_path('storage')))
                || (realpath(public_path('storage')) !== false
                    && realpath(public_path('storage')) === realpath(storage_path('app/public'))),
            'vite_development_server_absent' => ! file_exists(public_path('hot')),
        ];
    }

    public function failures(): array
    {
        return array_keys(array_filter($this->checks(), fn (bool $passed) => ! $passed));
    }
}
