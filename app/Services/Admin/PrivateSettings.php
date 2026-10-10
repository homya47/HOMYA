<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PrivateSettings
{
    public const KEYS = ['HOMYA_CONTACT_EMAIL', 'HOMYA_CONTACT_PHONE', 'HOMYA_BANK_TRANSFER_ENABLED', 'HOMYA_BANK_BENEFICIARY', 'HOMYA_BANK_IBAN', 'HOMYA_BANK_BIC', 'HOMYA_BANK_NAME', 'HOMYA_GOOGLE_CLIENT_ID', 'HOMYA_GOOGLE_CLIENT_SECRET', 'HOMYA_GOOGLE_REFRESH_TOKEN', 'HOMYA_GA4_PROPERTY_ID', 'HOMYA_GSC_SITE', 'HOMYA_GOOGLE_ADS_CUSTOMER_ID', 'HOMYA_GOOGLE_ADS_MANAGER_ID', 'HOMYA_GOOGLE_ADS_DEVELOPER_TOKEN', 'HOMYA_MERCHANT_ACCOUNT_ID', 'HOMYA_GTM_CONTAINER_ID', 'HOMYA_META_ACCOUNT_ID', 'HOMYA_META_ACCESS_TOKEN', 'HOMYA_META_API_VERSION'];

    public function version(): string
    {
        return hash_hmac('sha256', File::get(app()->environmentFilePath()), config('app.key'));
    }

    public function save(array $values, string $version): void
    {
        if (! app()->environment('local', 'testing') || app()->configurationIsCached() || array_diff(array_keys($values), self::KEYS)) {
            throw new \RuntimeException('SETTINGS_WRITE_REFUSED');
        }
        $path = app()->environmentFilePath();
        abort_if(is_link($path), 503);
        abort_if(is_link($path.'.admin-lock'), 503);
        $lock = fopen($path.'.admin-lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX)) {
            throw new \RuntimeException('SETTINGS_LOCK_FAILED');
        }
        try {
            chmod($path.'.admin-lock', 0600);
            if (! hash_equals($this->version(), $version)) {
                throw ValidationException::withMessages(['settings' => 'Les paramètres ont changé. Rechargez la page avant de continuer.']);
            }
            $before = File::get($path);
            $after = $before;
            foreach ($values as $key => $value) {
                if (! is_string($value) || preg_match('/[\r\n\x00\x27]/', $value) || strlen($value) > 4096) {
                    throw ValidationException::withMessages(['settings' => 'Valeur de configuration invalide.']);
                }
                if (preg_match_all('/^'.preg_quote($key, '/').'=/m', $after) > 1) {
                    throw new \RuntimeException('DUPLICATE_KEY');
                }
                $line = $key."='".$value."'";
                $after = preg_match('/^'.$key.'=.*$/m', $after) ? preg_replace_callback('/^'.$key.'=.*$/m', fn () => $line, $after) : rtrim($after)."\n".$line."\n";
            }
            $directory = storage_path('app/private/admin');
            File::ensureDirectoryExists($directory, 0700);
            $backup = $directory.'/settings-before-'.Str::uuid().'.encrypted';
            $this->write($backup, Crypt::encryptString($before));
            $temporary = $path.'.pending-'.Str::uuid();
            $this->write($temporary, $after);
            if (File::get($path) !== $before || ! rename($temporary, $path)) {
                throw new \RuntimeException('SETTINGS_CHANGED');
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function write(string $path, string $content): void
    {
        $handle = fopen($path, 'x');
        if (! $handle) {
            throw new \RuntimeException('SETTINGS_FILE_FAILED');
        }
        try {
            if (! chmod($path, 0600) || fwrite($handle, $content) !== strlen($content) || ! fflush($handle)) {
                throw new \RuntimeException('SETTINGS_FILE_FAILED');
            }
        } finally {
            fclose($handle);
        }
    }
}
