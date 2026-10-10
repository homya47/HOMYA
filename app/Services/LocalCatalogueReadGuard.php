<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Local HTTP reader only. Does not open the historical CLI guards to the web. */
final class LocalCatalogueReadGuard
{
    public const CONNECTION = 'homya_local_reader';

    private const ACCESS = 'storage/app/private/database-access/20261007T222611Z-9710d513-f606-49e5-a2a3-0d28926b3307/.env.homya_local_reader';

    public function requireContext(): void
    {
        if (! app()->isLocal() || app()->configurationIsCached() || is_file(app()->getCachedConfigPath()) || PHP_VERSION_ID < 80300
            || ! extension_loaded('pdo_mysql') || ! function_exists('posix_geteuid')) {
            throw new RuntimeException('LOCAL_READER_CONTEXT_REQUIRED');
        }
        $request = request();
        if (! in_array($request->getHost(), ['127.0.0.1', 'localhost', '[::1]'], true)
            || ! in_array($request->server('REMOTE_ADDR'), ['127.0.0.1', '::1'], true)
            || $request->headers->has('Forwarded') || $request->headers->has('X-Forwarded-For')
            || $request->headers->has('X-Forwarded-Host')) {
            throw new RuntimeException('LOOPBACK_REQUEST_REQUIRED');
        }
    }

    /** Pure parsing used by offline tests with synthetic credentials. */
    public function configurationFromText(string $text, array $config): array
    {
        $guard = new ArchiveDatabaseGuard;
        $guard->validateConfiguration(self::CONNECTION, $config);
        if (strlen($text) > 4096) {
            throw new RuntimeException('READER_ACCESS_INVALID');
        }
        $values = [];
        foreach (explode("\n", trim($text)) as $line) {
            if (! preg_match('/^(DB_[A-Z_]+)=([^\r\n]*)$/D', $line, $match) || isset($values[$match[1]])) {
                throw new RuntimeException('READER_ACCESS_INVALID');
            }
            $values[$match[1]] = $match[2];
        }
        $expected = ['DB_CONNECTION' => 'mariadb', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307',
            'DB_SOCKET' => config('homya.xampp_socket'), 'DB_DATABASE' => 'homya_local',
            'DB_USERNAME' => self::CONNECTION, 'DB_CHARSET' => 'utf8mb4', 'DB_COLLATION' => 'utf8mb4_bin'];
        if (count($values) !== count($expected) + 1 || ! preg_match('/^[a-f0-9]{64}$/D', $values['DB_PASSWORD'] ?? '')) {
            throw new RuntimeException('READER_ACCESS_INVALID');
        }
        foreach ($expected as $key => $value) {
            if (($values[$key] ?? null) !== $value) {
                throw new RuntimeException('READER_ACCESS_TARGET_INVALID');
            }
        }
        $config['password'] = $values['DB_PASSWORD'];
        $guard->validateConfiguration(self::CONNECTION, $config, true);

        return $config;
    }

    public function connect(): Connection
    {
        $this->requireContext();
        $configuration = config('database.connections.'.self::CONNECTION);
        (new ArchiveDatabaseGuard)->validateConfiguration(self::CONNECTION, $configuration);
        $root = PrivateLocalFiles::path(base_path(), 'storage/app/private/database-access');
        PrivateLocalFiles::requireMode($root, 0700, true);
        $path = PrivateLocalFiles::path(base_path(), self::ACCESS);
        PrivateLocalFiles::requireMode(dirname($path), 0700, true);
        PrivateLocalFiles::requireMode($path, 0600);
        $before = stat($path);
        if ($before['nlink'] !== 1) {
            throw new RuntimeException('READER_ACCESS_LINK_INVALID');
        }
        $text = file_get_contents($path);
        $config = $this->configurationFromText($text, $configuration);
        unset($text);
        clearstatcache(true, $path);
        PrivateLocalFiles::requireMode($path, 0600);
        $after = stat($path);
        foreach (['dev', 'ino', 'mode', 'size', 'mtime', 'ctime', 'nlink'] as $key) {
            if ($before[$key] !== $after[$key]) {
                throw new RuntimeException('READER_ACCESS_CHANGED');
            }
        }
        try {
            // No default connection or global config mutation; runtime instance is request-local.
            $connection = DB::connectUsing('homya_catalogue_web_reader', $config, true);
            $connection->setReconnector(static fn () => throw new RuntimeException('READER_RECONNECT_FORBIDDEN'));
            $server = $connection->selectOne('SELECT CURRENT_USER() AS identity, DATABASE() AS database_name,
                VERSION() AS version, @@socket AS socket, @@datadir AS datadir, @@port AS port,
                @@character_set_connection AS charset, @@collation_connection AS collation,
                @@global.max_allowed_packet AS packet_global, @@session.max_allowed_packet AS packet_session,
                @@session.sql_mode AS sql_mode, @@foreign_key_checks AS foreign_keys, @@check_constraint_checks AS checks');
            if (! is_object($server)) {
                throw new RuntimeException('READER_SERVER_INVALID');
            }
            (new ArchiveDatabaseGuard)->validateServer($server, self::CONNECTION, false);

            return $connection;
        } catch (\Throwable $error) {
            DB::purge('homya_catalogue_web_reader');
            throw new RuntimeException('LOCAL_READER_UNAVAILABLE');
        }
    }
}
