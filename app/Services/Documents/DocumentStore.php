<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Local, single-instance storage. Issuance commits the number, snapshot and PDF together. */
final class DocumentStore
{
    private function root(): string
    {
        $root = storage_path('app/private/commercial-documents');
        if (is_link($root)) {
            throw new RuntimeException('Unsafe document directory.');
        }
        File::ensureDirectoryExists($root, 0700, true);

        return $root;
    }

    private function locked(callable $callback, bool $write = false): mixed
    {
        $path = $this->root().'/.lock';
        if (is_link($path)) {
            throw new RuntimeException('Unsafe lock.');
        }
        $handle = fopen($path, 'c');
        if (! $handle) {
            throw new RuntimeException('Cannot open document lock.');
        }
        chmod($path, 0600);
        try {
            if (! flock($handle, $write ? LOCK_EX : LOCK_SH)) {
                throw new RuntimeException('Cannot lock documents.');
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function read(string $path): array
    {
        if (is_link($path) || ! is_file($path) || filesize($path) > 20000000) {
            throw new RuntimeException('Invalid document archive.');
        }

        return json_decode(Crypt::decryptString(file_get_contents($path)), true, 64, JSON_THROW_ON_ERROR);
    }

    private function commit(string $path, array $record): void
    {
        if (file_exists($path) || is_link($path)) {
            throw new RuntimeException('Document archive already exists.');
        }
        $temp = $this->root().'/.pending-'.Str::uuid();
        $handle = fopen($temp, 'x');
        if (! $handle) {
            throw new RuntimeException('Cannot create document archive.');
        }
        try {
            chmod($temp, 0600);
            $bytes = Crypt::encryptString(json_encode($record, JSON_THROW_ON_ERROR));
            $offset = 0;
            while ($offset < strlen($bytes)) {
                $written = fwrite($handle, substr($bytes, $offset));
                if (! $written) {
                    throw new RuntimeException('Cannot write document archive.');
                }
                $offset += $written;
            }
            fflush($handle);
            if (function_exists('fsync')) {
                fsync($handle);
            }
            fclose($handle);
            $handle = null;
            if (! rename($temp, $path)) {
                throw new RuntimeException('Cannot commit document archive.');
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }

    private function load(string $id): array
    {
        abort_unless(Str::isUuid($id), 404);
        $issued = $this->root().'/'.$id.'-issued.encrypted';
        if (is_file($issued)) {
            return $this->read($issued);
        }
        $files = glob($this->root().'/'.$id.'-draft-*.encrypted') ?: [];
        rsort($files, SORT_STRING);
        abort_unless($files !== [], 404);

        return $this->read($files[0]);
    }

    public function find(string $id): array
    {
        return $this->locked(fn () => $this->load($id));
    }

    private function records(): array
    {
        $records = [];
        foreach (glob($this->root().'/*.encrypted') ?: [] as $file) {
            if (! preg_match('/\A([a-f0-9-]{36})-(?:issued|draft-\d{8})\.encrypted\z/', basename($file), $match)) {
                throw new RuntimeException('Unrecognized document archive.');
            }
            // Do not ignore corrupt issued records: doing so could silently reuse an invoice number.
            $record = $this->read($file);
            if (isset($records[$match[1]]) && ($records[$match[1]]['status'] === 'issued' || $records[$match[1]]['revision'] > $record['revision'])) {
                continue;
            }
            $records[$match[1]] = $record;
        }

        return array_values($records);
    }

    public function all(): array
    {
        return $this->locked(function () {
            $records = $this->records();
            usort($records, fn ($a, $b) => strcmp($b['updated_at'], $a['updated_at']));
            foreach ($records as &$record) {
                unset($record['pdf']);
            }

            return $records;
        });
    }

    public function forOrder(string $orderId, bool $issuedOnly = true): array
    {
        return array_values(array_filter($this->all(), fn ($record) => $record['data']['order_id'] === $orderId && (! $issuedOnly || $record['status'] === 'issued')));
    }

    public function create(string $type, ?string $orderId = null, ?array $copy = null, ?array $source = null): array
    {
        $data = $copy ?? app(DocumentData::class)->blank($type, $orderId);
        abort_unless(isset(DocumentData::TYPES[$type]), 404);
        $data['type'] = $type;
        $data = app(DocumentData::class)->normalize($data, $data);

        return $this->locked(function () use ($data, $source) {
            $record = ['id' => (string) Str::uuid(), 'revision' => 1, 'status' => 'draft', 'number' => null, 'source_document' => $source, 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String(), 'data' => $data, 'history' => [['action' => 'created', 'at' => now()->toIso8601String()]]];
            $this->commit($this->root().'/'.$record['id'].'-draft-00000001.encrypted', $record);

            return $record;
        }, true);
    }

    public function save(string $id, int $revision, array $input): array
    {
        return $this->locked(function () use ($id, $revision, $input) {
            $record = $this->load($id);
            $this->editable($record, $revision);
            $record['data'] = app(DocumentData::class)->normalize($input, $record['data']);
            $record['revision']++;
            $record['updated_at'] = now()->toIso8601String();
            $record['history'][] = ['action' => 'saved', 'at' => $record['updated_at']];
            $this->commit($this->root().'/'.$id.'-draft-'.str_pad((string) $record['revision'], 8, '0', STR_PAD_LEFT).'.encrypted', $record);

            return $record;
        }, true);
    }

    private function editable(array $record, int $revision): void
    {
        if ($record['status'] !== 'draft' || $record['revision'] !== $revision) {
            throw ValidationException::withMessages(['document' => 'Ce document a changé ou a déjà été émis. Rechargez la page ; un document émis ne peut être modifié.']);
        }
    }

    private function series(array $records, string $type, int $year): ?array
    {
        $series = null;
        foreach ($records as $record) {
            if ($record['status'] === 'issued' && $record['data']['type'] === $type && $record['series']['year'] === $year) {
                if (! $series || $record['series']['sequence'] > $series['sequence']) {
                    $series = $record['series'];
                }
            }
        }

        return $series;
    }

    public function currentSeries(string $type): ?array
    {
        return $this->locked(fn () => $this->series($this->records(), $type, (int) now('Europe/Paris')->format('Y')));
    }

    public function issue(string $id, int $revision, array $numbering, ?callable $renderer = null): array
    {
        return $this->locked(function () use ($id, $revision, $numbering, $renderer) {
            $record = $this->load($id);
            if ($record['status'] === 'issued') {
                return $record; // Retry returns the exact committed package; never allocates a second number.
            }
            $this->editable($record, $revision);
            $data = app(DocumentData::class)->normalize($record['data'], $record['data']);
            $missing = app(DocumentData::class)->missing($data);
            if ($missing) {
                throw ValidationException::withMessages(['document' => $missing]);
            }
            $records = $this->records();
            if ($data['type'] === 'invoice' && $data['order_id']) {
                foreach ($records as $other) {
                    if ($other['status'] === 'issued' && $other['data']['type'] === 'invoice' && $other['data']['order_id'] === $data['order_id']) {
                        throw ValidationException::withMessages(['document' => 'Une facture existe déjà pour cette commande. Aucune seconde facture ne sera émise.']);
                    }
                }
            }
            $year = (int) now('Europe/Paris')->format('Y');
            $series = $this->series($records, $data['type'], $year);
            if ($series) {
                $series['sequence']++;
            } else {
                if (empty($numbering['series_confirmed']) || empty($numbering['prefix']) || ! preg_match('/\A[A-Z0-9][A-Z0-9-]{1,25}\z/', $numbering['prefix']) || (int) ($numbering['first_sequence'] ?? 0) < 1 || (int) $numbering['first_sequence'] > 99999999) {
                    throw ValidationException::withMessages(['numbering' => 'Confirmez le périmètre HOMYA / type / année et renseignez le préfixe et le premier numéro après vérification de l’historique.']);
                }
                $series = ['scope' => 'HOMYA/type/year', 'year' => $year, 'prefix' => $numbering['prefix'], 'sequence' => (int) $numbering['first_sequence'], 'confirmed_at' => now()->toIso8601String()];
            }
            if ($series['sequence'] > 99999999) {
                throw ValidationException::withMessages(['numbering' => 'La série est épuisée ; une nouvelle règle doit être définie.']);
            }
            $number = $series['prefix'].'-'.str_pad((string) $series['sequence'], 4, '0', STR_PAD_LEFT);
            foreach ($records as $other) {
                if (($other['number'] ?? null) === $number) {
                    throw ValidationException::withMessages(['numbering' => 'Ce numéro existe déjà dans les archives HOMYA.']);
                }
            }
            $record['data'] = $data;
            $record['issuer'] = app(DocumentData::class)->issuer();
            $record['status'] = 'issued';
            $record['number'] = $number;
            $record['series'] = $series;
            $record['issued_at'] = now('Europe/Paris')->toIso8601String();
            $record['updated_at'] = $record['issued_at'];
            $record['history'][] = ['action' => 'issued', 'at' => $record['issued_at'], 'actor' => config('admin.username'), 'review_confirmed' => true];
            $pdf = $renderer ? $renderer($record) : app(DocumentPdf::class)->render($record);
            if (! is_string($pdf) || ! str_starts_with($pdf, '%PDF-') || strlen($pdf) > 10000000) {
                throw new RuntimeException('Invalid generated PDF.');
            }
            $record['pdf'] = base64_encode($pdf);
            $record['pdf_sha256'] = hash('sha256', $pdf);
            $this->commit($this->root().'/'.$id.'-issued.encrypted', $record);

            return $record;
        }, true);
    }

    public function pdf(array $record): string
    {
        if ($record['status'] === 'draft') {
            $record['issuer'] = app(DocumentData::class)->issuer();

            return app(DocumentPdf::class)->render($record);
        }
        $pdf = base64_decode($record['pdf'], true);
        if (! is_string($pdf) || ! hash_equals($record['pdf_sha256'], hash('sha256', $pdf))) {
            throw new RuntimeException('Document integrity failure.');
        }

        return $pdf;
    }
}
