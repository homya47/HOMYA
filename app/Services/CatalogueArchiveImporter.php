<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use RuntimeException;
use Throwable;

/** Never resolved by the web; the caller must hold a separately authorised execution scope. */
class CatalogueArchiveImporter
{
    /** Never replay an archive write after an ambiguous connection loss. */
    public static function preventReplay(Connection $connection): void
    {
        $connection->setReconnector(static fn () => throw new RuntimeException('ARCHIVE_RECONNECT_FORBIDDEN'));
    }

    public function load(Connection $connection, CatalogueArchivePayload $payload): array
    {
        self::preventReplay($connection);
        app(ArchiveDatabaseGuard::class)->validateConnectedRole($connection, 'import');
        if ($connection->transactionLevel() !== 0) {
            throw new RuntimeException('IMPORT_TRANSACTION_CONTEXT_INVALID');
        }
        $payload->verifyFiles();
        $key = $payload->lotKey();
        $existing = $connection->table('archive_lots')->where('lot_key', $key)->first();
        if ($existing !== null) {
            return $this->existing($connection, $payload);
        }
        $registered = $this->now();
        try {
            $connection->table('archive_lots')->insert($payload->lotRow($registered));
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {
                return $this->existing($connection, $payload);
            }
            // The initial INSERT may have reached the server: do not replay or repair it.
            throw new RuntimeException('LOT_REGISTRATION_STATUS_TO_INSPECT');
        } catch (Throwable) {
            // A blocked reconnect can follow a write accepted by the server.
            throw new RuntimeException('LOT_REGISTRATION_STATUS_TO_INSPECT');
        }

        $connection->beginTransaction();
        $commitAttempted = false;
        try {
            $lot = $this->lock($connection, $key);
            if (($lot['registered_at'] ?? null) !== $registered || ($lot['load_state'] ?? null) !== 'prepared'
                || ($lot['verification_document'] ?? null) !== null || ($lot['verified_at'] ?? null) !== null) {
                throw new RuntimeException('LOT_REGISTRATION_CONFLICT');
            }
            $empty = $this->children($connection, $key);
            $payload->assertLotMatches($lot);
            if (array_filter($empty, fn ($rows) => $rows !== [])) {
                throw new RuntimeException('LOT_PARTIAL_REFUSED');
            }
            $this->state($connection, $key, ['load_state' => 'loading']);
            foreach (['archive_categories', 'archive_products', 'archive_translations'] as $table) {
                // One row/message: no dependency on a guessed batch capacity.
                foreach ($payload->rows($table) as $row) {
                    $connection->table($table)->insert($row);
                }
            }
            $lot = $this->lock($connection, $key);
            $children = $this->children($connection, $key);
            $payload->assertMatches($lot, ...array_values($children));
            $payload->verifyFiles();
            $verified = $this->now();
            $this->state($connection, $key, ['load_state' => 'verified', 'verification_document' => $payload->proof(), 'verified_at' => $verified]);
            $payload->assertVerified($this->lock($connection, $key));
            $commitAttempted = true;
            $connection->commit();

            return ['status' => 'ARCHIVE_VERIFIED_NOT_ACTIVATED', 'lot_key' => $key, 'counts' => $payload->counts(), 'activated' => false];
        } catch (Throwable) {
            if ($commitAttempted) {
                throw new RuntimeException('IMPORT_COMMIT_STATUS_TO_INSPECT');
            }
            try {
                $connection->rollBack();
                $this->markFailed($connection, $payload, $registered);
            } catch (Throwable) {
                throw new RuntimeException('IMPORT_STATUS_TO_INSPECT');
            }
            throw new RuntimeException('IMPORT_FAILED_NOT_ACTIVATED');
        }
    }

    private function existing(Connection $connection, CatalogueArchivePayload $payload): array
    {
        return $connection->transaction(function () use ($connection, $payload): array {
            $lot = $this->lock($connection, $payload->lotKey());
            $payload->assertVerified($lot); // Incomplete/prepared/failed are never resumed here.
            $payload->assertMatches($lot, ...array_values($this->children($connection, $payload->lotKey())));
            $payload->verifyFiles();

            return ['status' => 'ARCHIVE_ALREADY_VERIFIED_UNCHANGED', 'lot_key' => $payload->lotKey(), 'counts' => $payload->counts(), 'activated' => false];
        }, 1);
    }

    private function lock(Connection $connection, string $key): array
    {
        if ($connection->transactionLevel() !== 1) {
            throw new RuntimeException('PARENT_TRANSACTION_REQUIRED');
        }
        $row = $connection->table('archive_lots')->where('lot_key', $key)->lockForUpdate()->first();
        if ($row === null) {
            throw new RuntimeException('LOT_MISSING');
        }

        return (array) $row;
    }

    private function children(Connection $connection, string $key): array
    {
        $result = [];
        foreach (['archive_categories', 'archive_products', 'archive_translations'] as $table) {
            $result[$table] = $connection->table($table)->where('lot_key', $key)->get()->map(fn ($row) => (array) $row)->all();
        }

        return $result;
    }

    private function state(Connection $connection, string $key, array $values): void
    {
        $lot = $this->lock($connection, $key);
        if (($lot['load_state'] ?? null) === 'verified'
            || array_diff(array_keys($values), ['load_state', 'verification_document', 'verified_at'])) {
            throw new RuntimeException('VERIFIED_LOT_IMMUTABLE');
        }
        if ($connection->table('archive_lots')->where('lot_key', $key)->update($values) !== 1) {
            throw new RuntimeException('LOT_STATE_UPDATE_FAILED');
        }
    }

    private function markFailed(Connection $connection, CatalogueArchivePayload $payload, string $registered): void
    {
        $connection->transaction(function () use ($connection, $payload, $registered): void {
            $lot = $this->lock($connection, $payload->lotKey());
            $payload->assertLotMatches($lot);
            if (($lot['load_state'] ?? null) !== 'prepared' || ($lot['registered_at'] ?? null) !== $registered
                || ($lot['manifest_document'] ?? null) !== $payload->lotRow($registered)['manifest_document']
                || ($lot['verification_document'] ?? null) !== null || ($lot['verified_at'] ?? null) !== null
                || array_filter($this->children($connection, $payload->lotKey()), fn ($rows) => $rows !== [])) {
                throw new RuntimeException('FAILED_TRANSITION_UNSAFE');
            }
            $this->state($connection, $payload->lotKey(), ['load_state' => 'failed']);
        }, 1);
    }

    private function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }
}
