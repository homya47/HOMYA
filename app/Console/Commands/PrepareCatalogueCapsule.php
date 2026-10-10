<?php

namespace App\Console\Commands;

use App\Services\CatalogueCapsule;
use Illuminate\Console\Command;
use Throwable;

class PrepareCatalogueCapsule extends Command
{
    protected $signature = 'homya:catalogue-capsule {--copy : Créer une nouvelle capsule privée}
        {--expected-lot= : Clé issue de la simulation, obligatoire pour copier}
        {--verify= : Vérifier une capsule existante par sa clé SHA-256} {--progress : Afficher des compteurs sans données source}';

    protected $description = 'Simule, copie ou vérifie la capsule catalogue locale, sans SQL ni réseau.';

    public function handle(CatalogueCapsule $capsule): int
    {
        if (! app()->isLocal() || ($this->option('copy') && $this->option('verify') !== null)
            || ($this->option('copy') && ! preg_match('/^[a-f0-9]{64}$/D', (string) $this->option('expected-lot')))
            || (! $this->option('copy') && $this->option('expected-lot') !== null)
            || config('catalogue.capsule_parent_path') !== base_path(CatalogueCapsule::PARENT)
            || config('catalogue.capsule_review_reports') !== CatalogueCapsule::REPORTS) {
            $this->error('Opération refusée : environnement local et un seul mode requis.');

            return self::FAILURE;
        }
        $progress = $this->option('progress') ? function (string $phase, int $count): void {
            $this->line($phase.': '.$count);
        } : null;
        try {
            if ($this->option('verify') !== null) {
                $result = $capsule->verify((string) $this->option('verify'), $progress);
            } elseif ($this->option('copy')) {
                $result = $capsule->copy((string) $this->option('expected-lot'), $progress);
            } else {
                $result = $capsule->summary($capsule->inspect($progress));
            }
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Capsule non validée : contrôle interrompu. Aucun SQL ni activation ; tout staging créé reste privé et incomplet.');

            return self::FAILURE;
        }
    }
}
