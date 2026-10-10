<?php

namespace App\Services\Commerce;

use App\Services\PrivateLocalFiles;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Encrypted quarantine only: a client document is never a merchant payment receipt. */
final class TransferProof
{
    public function store(object $order, UploadedFile $file): void
    {
        $directory = 'commerce/transfer-proofs/'.$order->id;
        $bytes = file_get_contents($file->getRealPath());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (! in_array($mime, ['application/pdf', 'image/png', 'image/jpeg'], true)) {
            throw ValidationException::withMessages(['proof' => __('commerce.proof_invalid')]);
        }
        $hash = hash('sha256', $bytes);
        $disk = Storage::disk('local');
        $path = $directory.'/'.$hash.'.enc';
        // Refuse pre-existing linked components before any read, enumeration or write.
        PrivateLocalFiles::path(rtrim($disk->path(''), DIRECTORY_SEPARATOR), $path, false);
        if ($disk->exists($path)) {
            return;
        }
        if (count($disk->files($directory)) >= 3) {
            throw ValidationException::withMessages(['proof' => __('commerce.proof_limit')]);
        }
        $record = ['order_id' => $order->id, 'received_at' => now()->toIso8601String(),
            'status' => 'quarantined_unverified', 'mime' => $mime, 'sha256' => $hash,
            'bytes' => base64_encode($bytes)];
        abort_unless($disk->put($path, Crypt::encryptString(json_encode($record, JSON_THROW_ON_ERROR))), 503);
    }
}
