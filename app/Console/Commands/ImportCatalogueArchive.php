<?php

namespace App\Console\Commands;

use App\Services\ArchiveDatabaseGuard;
use App\Services\CatalogueArchiveImporter;
use App\Services\CatalogueArchivePayload;
use App\Services\CatalogueCapsule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportCatalogueArchive extends Command
{
    protected $signature = 'homya:catalogue-import {lot : Clé exacte de la capsule}
        {--execute : Exécution SQL, soumise à une autorisation distincte}
        {--operation= : OP-06 pour test ou OP-09 pour local, seulement après accord propre}
        {--connection= : Compte import dédié explicitement choisi}
        {--access= : Fichier privé du seul compte choisi}
        {--expected-lot= : Clé confirmée, obligatoire en exécution}
        {--progress : Compteurs de vérification des médias}';

    protected $description = 'Simule un import de capsule sans SQL par défaut ; aucune bascule du site.';

    public function handle(CatalogueCapsule $capsule, ArchiveDatabaseGuard $guard, CatalogueArchiveImporter $importer): int
    {
        $temporary = false;
        try {
            $key = (string) $this->argument('lot');
            if (! app()->isLocal() || ! preg_match('/^[a-f0-9]{64}$/D', $key)
                || config('catalogue.capsule_parent_path') !== base_path(CatalogueCapsule::PARENT)
                || config('catalogue.capsule_review_reports') !== CatalogueCapsule::REPORTS) {
                throw new \RuntimeException('IMPORT_LOCAL_CAPSULE_REQUIRED');
            }
            $execute = (bool) $this->option('execute');
            $name = (string) $this->option('connection');
            if (! $execute && ($name !== '' || $this->option('access') !== null || $this->option('operation') !== null || $this->option('expected-lot') !== null)) {
                throw new \RuntimeException('SIMULATION_ACCESS_FORBIDDEN');
            }
            if ($execute) {
                $guard->requireRole($name, 'import');
                if ($this->option('expected-lot') !== $key || $this->option('operation') !== ($name === 'homya_test_import' ? 'OP-06' : 'OP-09')) {
                    throw new \RuntimeException('EXPLICIT_OPERATION_REQUIRED');
                }
                $this->warn('SQL réel : uniquement sous autorisation distincte couvrant cette opération, cible et capsule.');
                if (! $this->input->isInteractive() || ! $this->confirm('Cette autorisation a-t-elle été obtenue pour ce chargement ?', false)) {
                    throw new \RuntimeException('EXECUTION_NOT_CONFIRMED');
                }
            }
            $progress = $this->option('progress') ? fn (string $phase, int $count) => $this->line($phase.': '.$count) : null;
            $payload = CatalogueArchivePayload::fromCapsule($capsule, $key, $progress);
            $payload->verifyFiles();
            if ($execute) {
                $configuration = $guard->configurationForRole($name, (string) $this->option('access'), 'import');
                $temporary = true;
                $connection = DB::connectUsing('homya_archive_import_execution', $configuration, true);
                unset($configuration);
                $result = $importer->load($connection, $payload);
            } else {
                $result = $payload->summary();
            }
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Opération interrompue. Aucune activation. En exécution, inspecter le lot et les preuves avant toute reprise ; aucun accès ni détail SQL affiché.');

            return self::FAILURE;
        } finally {
            if ($temporary) {
                DB::purge('homya_archive_import_execution');
            }
        }
    }
}
