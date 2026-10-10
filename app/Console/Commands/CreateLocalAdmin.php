<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class CreateLocalAdmin extends Command
{
    protected $signature = 'homya:admin-create {--generate : Generate credentials in a private local file} {--rotate : Replace an existing local credential deliberately}';

    protected $description = 'Create the local administrator without modifying the database';

    public function handle(): int
    {
        try {
            if (! app()->environment('local', 'testing') || app()->configurationIsCached()) {
                throw new \RuntimeException;
            }
            $path = app()->environmentFilePath();
            if (! is_file($path) || is_link($path)) {
                throw new \RuntimeException;
            }
            $before = File::get($path);
            if (preg_match('/^HOMYA_ADMIN_PASSWORD_HASH=.+$/m', $before) && ! $this->option('rotate')) {
                $this->error('Un compte existe déjà. Utilisez --rotate uniquement pour remplacer volontairement ses accès.');

                return self::FAILURE;
            }
            $generated = (bool) $this->option('generate');
            $username = $generated ? 'admin' : $this->ask('Identifiant administrateur', 'admin');
            $password = $generated ? Str::password(32, spaces: false) : $this->secret('Mot de passe (au moins 16 caractères)');
            if (! is_string($username) || ! preg_match('/\A[a-zA-Z0-9._-]{3,80}\z/', $username) || ! is_string($password) || strlen($password) < 16 || strlen($password) > 72) {
                $this->error('Identifiant ou longueur du mot de passe invalide.');

                return self::FAILURE;
            }
            $directory = storage_path('app/private/admin');
            if (is_link($directory)) {
                throw new \RuntimeException;
            }
            File::ensureDirectoryExists($directory, 0700);
            $stamp = now()->utc()->format('YmdHis').'-'.Str::random(8);
            $this->privateWrite($directory.'/env-before-'.$stamp, $before);
            // The handoff file is private, never returned in the command output or HTTP responses.
            if ($generated) {
                $this->privateWrite($directory.'/access-'.$stamp.'.txt', "HOMYA — Administration locale\n\nLien : ".rtrim(config('app.url'), '/')."/admin\nIdentifiant : ".$username."\nMot de passe : ".$password."\n\nFichier privé. Conservez ces accès dans votre gestionnaire de mots de passe.\n");
            }
            $after = $before;
            foreach (['HOMYA_ADMIN_USERNAME' => $username, 'HOMYA_ADMIN_PASSWORD_HASH' => Hash::make($password)] as $key => $value) {
                if (preg_match_all('/^'.preg_quote($key, '/').'=/m', $after) > 1) {
                    throw new \RuntimeException;
                }
                $line = $key."='".$value."'";
                $after = preg_match('/^'.$key.'=.*$/m', $after) ? preg_replace_callback('/^'.$key.'=.*$/m', fn () => $line, $after) : rtrim($after)."\n".$line."\n";
            }
            $temporary = $path.'.admin-'.$stamp;
            $this->privateWrite($temporary, $after);
            if (File::get($path) !== $before || ! rename($temporary, $path)) {
                throw new \RuntimeException;
            }
            $this->info('Compte local créé. Mot de passe haché dans .env. Aucune opération SQL.');
            if ($generated) {
                $this->line('Accès conservés dans le fichier privé storage/app/private/admin/access-'.$stamp.'.txt');
            }

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Création interrompue : vérifiez les permissions locales et la configuration, sans publier de secrets.');

            return self::FAILURE;
        }
    }

    private function privateWrite(string $path, string $content): void
    {
        $handle = fopen($path, 'x');
        if ($handle === false) {
            throw new \RuntimeException;
        }
        try {
            if (! chmod($path, 0600) || fwrite($handle, $content) !== strlen($content) || ! fflush($handle)) {
                throw new \RuntimeException;
            }
        } finally {
            fclose($handle);
        }
    }
}
