<?php

namespace App\Console\Commands;

use App\Models\BatimentParcelleActuel;
use App\Models\BatimentParcelleRapprochement;
use App\Models\CompteurParcelleActuel;
use App\Models\CompteurParcelleRapprochement;
use App\Models\InstallationBatimentCourant;
use App\Models\InstallationBatimentEvenement;
use App\Models\InstallationCompteurCourant;
use App\Models\InstallationCompteurEvenement;
use App\Models\InstallationEtat;
use App\Models\InstallationEtatCourant;
use App\Models\InstallationParcelleCourante;
use App\Models\InstallationParcelleEvenement;
use App\Models\InstallationProprietaireCourante;
use App\Models\InstallationProprietaireEvenement;
use App\Models\ProprietaireParcelleActuel;
use App\Models\ProprietaireParcelleRapprochement;
use App\Observers\BatimentParcelleRapprochementObserver;
use App\Observers\CompteurParcelleRapprochementObserver;
use App\Observers\InstallationBatimentEvenementObserver;
use App\Observers\InstallationCompteurEvenementObserver;
use App\Observers\InstallationEtatObserver;
use App\Observers\InstallationParcelleEvenementObserver;
use App\Observers\InstallationProprietaireEvenementObserver;
use App\Observers\ProprietaireParcelleRapprochementObserver;
use Illuminate\Console\Command;

/**
 * Les tables de projection ne sont que des caches jetables : cette commande
 * les régénère entièrement en rejouant le log d'évènements immuable, qui
 * reste la seule source de vérité.
 */
class RebuildProjectionsCommand extends Command
{
    protected $signature = 'projections:rebuild';

    protected $description = "Régénère les tables d'état courant à partir du log d'évènements";

    public function handle(): int
    {
        $this->rebuild(InstallationEtatCourant::class, InstallationEtat::class, new InstallationEtatObserver);
        $this->rebuild(InstallationParcelleCourante::class, InstallationParcelleEvenement::class, new InstallationParcelleEvenementObserver);
        $this->rebuild(InstallationBatimentCourant::class, InstallationBatimentEvenement::class, new InstallationBatimentEvenementObserver);
        $this->rebuild(InstallationCompteurCourant::class, InstallationCompteurEvenement::class, new InstallationCompteurEvenementObserver);
        $this->rebuild(InstallationProprietaireCourante::class, InstallationProprietaireEvenement::class, new InstallationProprietaireEvenementObserver);
        $this->rebuild(BatimentParcelleActuel::class, BatimentParcelleRapprochement::class, new BatimentParcelleRapprochementObserver);
        $this->rebuild(ProprietaireParcelleActuel::class, ProprietaireParcelleRapprochement::class, new ProprietaireParcelleRapprochementObserver);
        $this->rebuild(CompteurParcelleActuel::class, CompteurParcelleRapprochement::class, new CompteurParcelleRapprochementObserver);

        $this->info('Projections régénérées.');

        return self::SUCCESS;
    }

    private function rebuild(string $projectionModel, string $evenementModel, object $observer): void
    {
        $projectionModel::query()->delete();

        $this->components->task(
            "Rejeu de {$evenementModel} -> {$projectionModel}",
            function () use ($evenementModel, $observer) {
                $evenementModel::query()->orderBy('id')->chunkById(500, function ($evenements) use ($observer) {
                    foreach ($evenements as $evenement) {
                        $observer->created($evenement);
                    }
                });
            }
        );
    }
}
