<?php

namespace App\Console\Commands;

use App\Services\ArchiveDatabaseGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckLocalDatabase extends Command
{
    protected $signature = 'homya:db-check {--connection= : Connexion dédiée explicitement sélectionnée}
        {--access= : Fichier privé correspondant à cette connexion} {--auth-only : Authentification sans sélection de base}';

    protected $description = 'Vérifie la connexion locale XAMPP en lecture seule, sans migration.';

    public function handle(ArchiveDatabaseGuard $guard): int
    {
        if (! app()->isLocal()) {
            $this->error('Ce diagnostic est réservé à l’environnement local.');

            return self::FAILURE;
        }

        $temporary = false;
        try {
            $named = $this->option('connection');
            $authOnly = (bool) $this->option('auth-only');
            if ($named) {
                $configuration = $guard->configuration($named, (string) $this->option('access'));
                if ($authOnly) {
                    $configuration['database'] = '';
                }
                $temporary = true;
                // No credential placed in Laravel's configuration repository or dynamic-connection cache.
                $connection = DB::connectUsing('homya_archive_diagnostic', $configuration, true);
                unset($configuration);
            } else {
                if ($this->option('access') || $authOnly || config('database.default') !== 'mariadb') {
                    $this->error('Diagnostic interrompu : sélection explicite ou connexion MariaDB locale requise.');

                    return self::FAILURE;
                }
                $configuration = config('database.connections.mariadb');
                $named = $configuration['username'] ?? '';
                // Runtime must never use the schema/import accounts.
                if ($named !== 'homya_local_reader') {
                    $this->error('Diagnostic interrompu : lecteur local non configuré.');

                    return self::FAILURE;
                }
                $guard->validateConfiguration($named, $configuration, true);
                $connection = DB::connection('mariadb');
            }
            $server = $connection->selectOne('SELECT CURRENT_USER() AS identity, DATABASE() AS database_name,
                VERSION() AS version, @@socket AS socket, @@datadir AS datadir, @@port AS port,
                @@character_set_connection AS charset, @@collation_connection AS collation,
                @@global.max_allowed_packet AS packet_global, @@session.max_allowed_packet AS packet_session,
                @@session.sql_mode AS sql_mode, @@foreign_key_checks AS foreign_keys, @@check_constraint_checks AS checks');
            $guard->validateServer($server, $named, $authOnly);
        } catch (Throwable) {
            $this->error('Connexion non vérifiée : cible, accès, session ou schéma indisponible. Aucun détail d’accès affiché.');

            return self::FAILURE;
        } finally {
            if ($temporary) {
                DB::purge('homya_archive_diagnostic');
            }
        }

        $this->info($authOnly
            ? 'Authentification XAMPP vérifiée sans base sélectionnée. Droits de lecture et catalogue non vérifiés.'
            : 'Connexion au schéma XAMPP vérifiée. Tables, droits de lecture et catalogue non vérifiés.');

        return self::SUCCESS;
    }
}
