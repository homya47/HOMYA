<?php

namespace App\Services;

use Illuminate\Database\Connection;
use RuntimeException;

final class ArchiveDatabaseGuard
{
    public function requireRole(string $name, string $role): void
    {
        if (! app()->isLocal() || ! app()->runningInConsole()) {
            throw new RuntimeException('LOCAL_CLI_REQUIRED');
        }
        if (! in_array($role, ['reader', 'import', 'schema'], true)
            || ! preg_match('/^homya_(test|local)_'.preg_quote($role, '/').'$/D', $name)) {
            throw new RuntimeException('CONNECTION_ROLE_INVALID');
        }
    }

    public function configurationForRole(string $name, string $accessPath, string $role): array
    {
        $this->requireRole($name, $role);

        return $this->configuration($name, $accessPath);
    }

    /** Explicit CLI consumers only. No implicit/default connection or credential loader. */
    public function validateConnectedRole(Connection $connection, string $role): void
    {
        $config = $connection->getConfig();
        $name = $config['username'] ?? '';
        $this->requireRole($name, $role);
        unset($config['name']); // Laravel adds the ephemeral connection name.
        $this->validateConfiguration($name, $config, true);
        $server = $connection->selectOne('SELECT CURRENT_USER() AS identity, DATABASE() AS database_name,
            VERSION() AS version, @@socket AS socket, @@datadir AS datadir, @@port AS port,
            @@character_set_connection AS charset, @@collation_connection AS collation,
            @@global.max_allowed_packet AS packet_global, @@session.max_allowed_packet AS packet_session,
            @@session.sql_mode AS sql_mode, @@foreign_key_checks AS foreign_keys, @@check_constraint_checks AS checks');
        if (! is_object($server)) {
            throw new RuntimeException('SERVER_TARGET_INVALID');
        }
        $this->validateServer($server, $name, false);
    }

    public function configuration(string $name, string $accessPath): array
    {
        if (! app()->isLocal() || ! app()->runningInConsole()) {
            throw new RuntimeException('LOCAL_CLI_REQUIRED');
        }
        $config = config('database.connections.'.$name);
        $this->validateConfiguration($name, $config);
        $base = config('homya.database_access_path');
        if (! is_string($base) || ! str_starts_with($accessPath, $base.'/')) {
            throw new RuntimeException('ACCESS_PATH_INVALID');
        }
        if (PrivateLocalFiles::path(base_path(), 'storage/app/private/database-access') !== $base) {
            throw new RuntimeException('ACCESS_ROOT_INVALID');
        }
        $relative = substr($accessPath, strlen($base) + 1);
        $path = PrivateLocalFiles::path($base, $relative);
        if (basename($path) !== '.env.'.$name) {
            throw new RuntimeException('ACCESS_NAME_INVALID');
        }
        PrivateLocalFiles::requireMode($base, 0700, true);
        $parent = $base;
        foreach (explode('/', dirname($relative)) as $part) {
            if ($part !== '.') {
                $parent .= '/'.$part;
                PrivateLocalFiles::requireMode($parent, 0700, true);
            }
        }
        PrivateLocalFiles::requireMode($path, 0600);
        $before = stat($path);
        $text = @file_get_contents($path);
        if (! is_string($text) || strlen($text) > 4096) {
            throw new RuntimeException('ACCESS_READ_FAILED');
        }
        $values = [];
        foreach (explode("\n", trim($text)) as $line) {
            if (! preg_match('/^(DB_[A-Z_]+)=([^\r\n]*)$/D', $line, $match) || isset($values[$match[1]])) {
                throw new RuntimeException('ACCESS_FORMAT_INVALID');
            }
            $values[$match[1]] = $match[2];
        }
        $expected = [
            'DB_CONNECTION' => 'mariadb', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3307',
            'DB_SOCKET' => config('homya.xampp_socket'), 'DB_DATABASE' => $config['database'],
            'DB_USERNAME' => $name, 'DB_CHARSET' => 'utf8mb4', 'DB_COLLATION' => 'utf8mb4_bin',
        ];
        if (count($values) !== count($expected) + 1 || ! preg_match('/^[a-f0-9]{64}$/D', $values['DB_PASSWORD'] ?? '')) {
            throw new RuntimeException('ACCESS_VALUES_INVALID');
        }
        foreach ($expected as $key => $value) {
            if (($values[$key] ?? null) !== $value) {
                throw new RuntimeException('ACCESS_TARGET_INVALID');
            }
        }
        clearstatcache(true, $path);
        PrivateLocalFiles::requireMode($path, 0600);
        $after = stat($path);
        $fields = array_flip(['dev', 'ino', 'mode', 'size', 'mtime', 'ctime']);
        if (array_intersect_key($after, $fields) !== array_intersect_key($before, $fields)) {
            throw new RuntimeException('ACCESS_CHANGED');
        }
        $config['password'] = $values['DB_PASSWORD'];
        $this->validateConfiguration($name, $config, true);

        return $config;
    }

    public function validateConfiguration(string $name, mixed $config, bool $withSecret = false): void
    {
        if (! preg_match('/^homya_(test|local)_(reader|import|schema)$/D', $name, $match) || ! is_array($config)) {
            throw new RuntimeException('CONNECTION_NOT_ALLOWED');
        }
        $expected = [
            'driver' => 'mariadb', 'host' => '127.0.0.1', 'port' => '3307',
            'unix_socket' => config('homya.xampp_socket'), 'database' => 'homya_'.$match[1],
            'username' => $name, 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_bin',
            'strict' => true, 'prefix' => '', 'url' => null, 'prefix_indexes' => true, 'engine' => null,
        ];
        foreach ($expected as $key => $value) {
            if (! array_key_exists($key, $config) || $config[$key] !== $value) {
                throw new RuntimeException('CONNECTION_TARGET_INVALID');
            }
        }
        $allowed = [...array_keys($expected), 'password', 'options'];
        if (array_diff(array_keys($config), $allowed)
            || filled(config('database.connections.mariadb.url')) || ($config['options'] ?? []) !== []
            || ($withSecret && ! preg_match('/^[a-f0-9]{64}$/D', $config['password'] ?? ''))) {
            throw new RuntimeException('CONNECTION_OPTIONS_INVALID');
        }
        $socket = $config['unix_socket'];
        if (! is_string($socket) || is_link($socket) || @filetype($socket) !== 'socket') {
            throw new RuntimeException('XAMPP_SOCKET_UNAVAILABLE');
        }
    }

    /** Called only after an explicitly requested live diagnostic; tested with doubles in OP-04. */
    public function validateServer(object $server, string $name, bool $authOnly): void
    {
        if (! preg_match('/^homya_(test|local)_(reader|import|schema)$/D', $name, $match)
            || ! property_exists($server, 'database_name')
            || ($server->identity ?? null) !== $name.'@localhost'
            || ($server->database_name ?? null) !== ($authOnly ? null : 'homya_'.$match[1])
            || ($server->version ?? null) !== config('homya.xampp_version')
            || ($server->socket ?? null) !== config('homya.xampp_socket')
            || ($server->datadir ?? null) !== config('homya.xampp_datadir')
            || (string) ($server->port ?? '') !== '3307'
            || ($server->charset ?? null) !== 'utf8mb4'
            || ($server->collation ?? null) !== 'utf8mb4_bin'
            || (int) ($server->packet_global ?? 0) < config('homya.minimum_packet_bytes')
            || (int) ($server->packet_session ?? 0) < config('homya.minimum_packet_bytes')
            || (int) ($server->foreign_keys ?? 0) !== 1 || (int) ($server->checks ?? 0) !== 1) {
            throw new RuntimeException('SERVER_TARGET_INVALID');
        }
        $modes = explode(',', $server->sql_mode ?? '');
        foreach (['STRICT_TRANS_TABLES', 'NO_ZERO_IN_DATE', 'NO_ZERO_DATE', 'ERROR_FOR_DIVISION_BY_ZERO', 'NO_ENGINE_SUBSTITUTION'] as $mode) {
            if (! in_array($mode, $modes, true)) {
                throw new RuntimeException('SERVER_SESSION_INVALID');
            }
        }
    }
}
