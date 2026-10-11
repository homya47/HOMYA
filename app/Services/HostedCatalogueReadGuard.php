<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Production-only SQL reader. Database permissions must independently be SELECT-only. */
final class HostedCatalogueReadGuard
{
    public const CONNECTION = 'homya_hosted_catalogue_reader';

    public function connect(): Connection
    {
        if (! app()->environment('production') || PHP_VERSION_ID < 80300 || ! extension_loaded('pdo_mysql')) {
            throw new RuntimeException('HOSTED_READER_CONTEXT_REQUIRED');
        }
        // The one-time inventory installer runs over SSH rather than an HTTPS request.
        // Keep this exception limited to that exact Artisan command; all HTTP reads
        // still require the canonical production host and HTTPS.
        $inventoryInstall = app()->runningInConsole()
            && ($_SERVER['argv'][1] ?? null) === 'homya:commerce-inventory';
        $request = request();
        if (! $inventoryInstall && ($request->getHost() !== ProductionSecurity::HOST || ! $request->isSecure())) {
            throw new RuntimeException('HOSTED_READER_ORIGIN_REQUIRED');
        }

        $reader = config('database.connections.'.self::CONNECTION);
        $writer = config('database.connections.mariadb');
        self::validateTarget($reader, $writer);

        try {
            $connection = DB::connectUsing(self::CONNECTION, $reader, true);
            $connection->setReconnector(static fn () => throw new RuntimeException('HOSTED_READER_RECONNECT_FORBIDDEN'));
            $server = $connection->selectOne('SELECT CURRENT_USER() AS identity, DATABASE() AS database_name,
                VERSION() AS version, @@character_set_connection AS charset, @@collation_connection AS collation,
                @@session.sql_mode AS sql_mode, @@foreign_key_checks AS foreign_keys, @@check_constraint_checks AS checks');
            if (! is_object($server) || ! str_starts_with((string) ($server->identity ?? ''), $reader['username'].'@')
                || ($server->database_name ?? null) !== $reader['database']
                || ! str_contains((string) ($server->version ?? ''), 'MariaDB')
                || ($server->charset ?? null) !== 'utf8mb4' || ($server->collation ?? null) !== 'utf8mb4_bin'
                || (int) ($server->foreign_keys ?? 0) !== 1 || (int) ($server->checks ?? 0) !== 1) {
                throw new RuntimeException('HOSTED_READER_SERVER_INVALID');
            }
            foreach (['STRICT_TRANS_TABLES', 'NO_ZERO_IN_DATE', 'NO_ZERO_DATE', 'ERROR_FOR_DIVISION_BY_ZERO', 'NO_ENGINE_SUBSTITUTION'] as $mode) {
                if (! in_array($mode, explode(',', (string) ($server->sql_mode ?? '')), true)) {
                    throw new RuntimeException('HOSTED_READER_SESSION_INVALID');
                }
            }

            return $connection;
        } catch (\Throwable) {
            DB::purge(self::CONNECTION);
            throw new RuntimeException('HOSTED_READER_UNAVAILABLE');
        }
    }

    /** Pure target checks used before a network connection is attempted. */
    public static function validateTarget(mixed $reader, mixed $writer): void
    {
        if (! is_array($reader) || ! is_array($writer)
            || ($reader['driver'] ?? null) !== 'mariadb' || ($reader['url'] ?? null) !== null
            || ! in_array($reader['host'] ?? null, ['localhost', '127.0.0.1'], true)
            || (string) ($reader['port'] ?? '') !== '3306'
            || ! is_string($reader['database'] ?? null)
            || ! preg_match('/^[A-Za-z0-9_]+$/D', $reader['database'])
            || ! is_string($writer['database'] ?? null) || $writer['database'] === ''
            || ! is_string($reader['username'] ?? null) || $reader['username'] === ''
            || ! is_string($writer['username'] ?? null) || $writer['username'] === ''
            || ($reader['username'] ?? null) === ($writer['username'] ?? null)
            || ! is_string($reader['password'] ?? null) || $reader['password'] === ''
            || ($reader['charset'] ?? null) !== 'utf8mb4' || ($reader['collation'] ?? null) !== 'utf8mb4_bin'
            || ($reader['strict'] ?? null) !== true || ($reader['options'] ?? null) !== []) {
            throw new RuntimeException('HOSTED_READER_TARGET_INVALID');
        }
    }
}
