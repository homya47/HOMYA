<?php

namespace App\Console\Commands;

use App\Services\ArchiveDatabaseGuard;
use App\Services\CatalogueArchiveSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PrepareCatalogueSchema extends Command
{
    protected $signature = 'homya:catalogue-schema {--ddl : Afficher le DDL compilé sans connexion}
        {--execute : Migrations ciblées, seulement après autorisation distincte}
        {--operation= : OP-05 test ou OP-08 local}
        {--connection= : Compte schema dédié explicite} {--access= : Fichier privé correspondant}
        {--expected-ddl= : Empreinte DDL approuvée} {--expected-files= : Empreinte des fichiers approuvés}';

    protected $description = 'Prépare le schéma hors SQL par défaut ; migrations ciblées après accord distinct.';

    public function handle(CatalogueArchiveSchema $schema): int
    {
        if (! app()->isLocal()) {
            $this->error('Préparation réservée à l’environnement local.');

            return self::FAILURE;
        }
        $temporary = false;
        $migrator = null;
        $previousDefault = config('database.default');
        try {
            $ddl = $schema->ddl();
            $files = $schema->fileDigest();
            if ($this->option('execute')) {
                if ($this->option('ddl')) {
                    throw new RuntimeException('SCHEMA_MODE_CONFLICT');
                }
                $guard = app(ArchiveDatabaseGuard::class);
                $name = (string) $this->option('connection');
                $guard->requireRole($name, 'schema');
                if ($this->option('operation') !== ($name === 'homya_test_schema' ? 'OP-05' : 'OP-08')
                    || $this->option('expected-ddl') !== hash('sha256', $ddl) || $this->option('expected-files') !== $files
                    || ! $this->input->isInteractive()
                    || ! $this->confirm('Autorisation distincte obtenue pour ces seules migrations, journal préparé et droits vérifiés ?', false)) {
                    throw new RuntimeException('SCHEMA_EXECUTION_SCOPE_REQUIRED');
                }
                $configuration = $guard->configurationForRole($name, (string) $this->option('access'), 'schema');
                $temporary = true;
                $connection = DB::connectUsing('homya_archive_schema_execution', $configuration, true);
                unset($configuration);
                $guard->validateConnectedRole($connection, 'schema');
                $tables = $connection->getSchemaBuilder()->getTables();
                if (array_column($tables, 'name') !== ['migrations'] || $connection->table('migrations')->count() !== 0
                    || $connection->transactionLevel() !== 0 || glob(database_path('schema/*'))) {
                    throw new RuntimeException('SCHEMA_EMPTY_JOURNAL_REQUIRED');
                }
                if ($schema->fileDigest() !== $files) {
                    throw new RuntimeException('MIGRATION_FILES_CHANGED');
                }
                app()->instance(CatalogueArchiveSchema::class, CatalogueArchiveSchema::forExecution($connection, hash('sha256', $ddl)));
                $migrator = app('migrator');
                $migrator->setConnection('homya_archive_schema_execution');
                // Direct Migrator: no MigrateCommand auto-journal, database creation, dump or seed.
                $migrator->run([database_path('migrations/catalogue')], ['pretend' => false, 'step' => false]);
                $expectedTables = ['migrations', ...CatalogueArchiveSchema::TABLES];
                $actualTables = array_column($connection->getSchemaBuilder()->getTables(), 'name');
                sort($expectedTables);
                sort($actualTables);
                $expectedMigrations = array_map(fn ($file) => substr($file, 0, -4), CatalogueArchiveSchema::MIGRATIONS);
                $recordedMigrations = $connection->table('migrations')->orderBy('migration')->pluck('migration')->all();
                if ($actualTables !== $expectedTables || $connection->table('migrations')->count() !== 4 || $schema->fileDigest() !== $files) {
                    throw new RuntimeException('SCHEMA_POSTCONTROL_FAILED');
                }
                if ($recordedMigrations !== $expectedMigrations) {
                    throw new RuntimeException('MIGRATION_JOURNAL_MISMATCH');
                }
                $this->line(json_encode(['status' => 'CATALOGUE_MIGRATIONS_COMPLETED_NEEDS_INDEPENDENT_REVIEW', 'tables' => $actualTables,
                    'sql_executed' => true, 'import_executed' => false], JSON_THROW_ON_ERROR));
            } elseif ($this->option('ddl')) {
                $this->rejectExecutionOptions();
                $this->line($ddl);
            } else {
                $this->rejectExecutionOptions();
                $this->line(json_encode(['status' => 'DDL_COMPILED_NO_SQL', 'tables' => CatalogueArchiveSchema::TABLES,
                    'sha256' => hash('sha256', $ddl), 'files_sha256' => $files, 'migrations' => CatalogueArchiveSchema::MIGRATIONS,
                    'bytes' => strlen($ddl), 'sql_executed' => false], JSON_THROW_ON_ERROR));
            }

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error($temporary
                ? 'Migration interrompue : inspecter les objets/journal ; aucun rollback, nettoyage ou import automatique.'
                : 'Préparation refusée avant connexion. Aucun SQL ni migration exécutés.');

            return self::FAILURE;
        } finally {
            app()->instance(CatalogueArchiveSchema::class, $schema);
            if ($migrator !== null) {
                $migrator->setConnection($previousDefault);
            }
            if ($temporary) {
                DB::purge('homya_archive_schema_execution');
            }
        }
    }

    private function rejectExecutionOptions(): void
    {
        foreach (['operation', 'connection', 'access', 'expected-ddl', 'expected-files'] as $option) {
            if ($this->option($option) !== null) {
                throw new RuntimeException('OFFLINE_EXECUTION_OPTIONS_FORBIDDEN');
            }
        }
    }
}
