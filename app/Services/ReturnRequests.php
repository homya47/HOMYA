<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class ReturnRequests
{
    public function save(array $data): array
    {
        $id = (string) Str::uuid();
        $record = ['id' => $id, 'submitted_at' => now()->utc()->toIso8601String(), 'status' => 'received', 'locale' => app()->getLocale(), 'declaration' => $data];
        File::ensureDirectoryExists(storage_path(), 0700);
        $directory = PrivateLocalFiles::path(storage_path(), 'app/private/returns', false);
        File::ensureDirectoryExists($directory, 0700);
        $path = $directory.'/'.$id.'.encrypted';
        $handle = fopen($path, 'x');
        if ($handle === false) {
            throw new \RuntimeException('RETURN_STORAGE_UNAVAILABLE');
        }
        try {
            if (! chmod($path, 0600)) {
                throw new \RuntimeException('RETURN_STORAGE_UNAVAILABLE');
            }
            $encrypted = Crypt::encryptString(json_encode($record, JSON_THROW_ON_ERROR));
            if (fwrite($handle, $encrypted) !== strlen($encrypted) || ! fflush($handle)) {
                throw new \RuntimeException('RETURN_STORAGE_UNAVAILABLE');
            }
        } finally {
            fclose($handle);
        }

        return $record;
    }

    public function find(string $id): array
    {
        abort_unless(Str::isUuid($id), 404);
        try {
            $path = PrivateLocalFiles::path(storage_path(), 'app/private/returns/'.$id.'.encrypted');
        } catch (\RuntimeException) {
            abort(404);
        }
        abort_unless(is_file($path) && ! is_link($path), 404);

        return json_decode(Crypt::decryptString(File::get($path)), true, 32, JSON_THROW_ON_ERROR);
    }
}
