<?php

namespace App\Console\Commands;

use App\Models\CompteurParcelleActuel;
use App\Models\InstallationCompteurCourant;
use App\Models\InstallationParcelleCourante;
use App\Models\RapprochementPropose;
use Illuminate\Console\Command;

/**
 * Contrairement au propriétaire (plusieurs légitimes par installation), un
 * compteur dessert une installation précise : si la parcelle porte plusieurs
 * installations, on ne sait pas laquelle le compteur dessert réellement.
 * La confiance est alors plafonnée sous 1.0 pour forcer une validation
 * humaine au lieu d'un lien automatique sur la mauvaise installation.
 */
class ProposerCompteursCommand extends Command
{
    protected $signature = 'installations:proposer-compteurs';

    protected $description = "Propose de lier le compteur d'une parcelle a l'installation qui porte cette meme parcelle (file a valider dans Filament)";

    private const CONFIANCE_MAX_PARCELLE_PARTAGEE = 0.5;

    public function handle(): int
    {
        $liens = InstallationParcelleCourante::all(['installation_id', 'parcelle_id']);

        $nbInstallationsParParcelle = InstallationParcelleCourante::query()
            ->selectRaw('parcelle_id, count(distinct installation_id) as nb')
            ->groupBy('parcelle_id')
            ->pluck('nb', 'parcelle_id');

        $this->info("{$liens->count()} liens installation-parcelle a examiner.");

        $creees = 0;
        $dejaLiees = 0;
        $dejaProposees = 0;
        $sansCandidat = 0;

        $bar = $this->output->createProgressBar($liens->count());
        $bar->start();

        foreach ($liens as $lien) {
            $bar->advance();

            $candidats = CompteurParcelleActuel::where('parcelle_id', $lien->parcelle_id)->get();

            if ($candidats->isEmpty()) {
                $sansCandidat++;

                continue;
            }

            $parcellePartagee = ($nbInstallationsParParcelle[$lien->parcelle_id] ?? 1) > 1;

            foreach ($candidats as $candidat) {
                $dejaLie = InstallationCompteurCourant::where('installation_id', $lien->installation_id)
                    ->where('numero_compteur', $candidat->numero_compteur)
                    ->exists();

                if ($dejaLie) {
                    $dejaLiees++;

                    continue;
                }

                $dejaPropose = RapprochementPropose::where('installation_id', $lien->installation_id)
                    ->where('type_cible', 'compteur')
                    ->where('cible_id', $candidat->numero_compteur)
                    ->exists();

                if ($dejaPropose) {
                    $dejaProposees++;

                    continue;
                }

                RapprochementPropose::create([
                    'installation_id' => $lien->installation_id,
                    'type_cible' => 'compteur',
                    'cible_id' => $candidat->numero_compteur,
                    'methode' => $parcellePartagee ? 'parcelle_partagee' : 'parcelle_unique',
                    'confiance' => $parcellePartagee
                        ? min($candidat->confiance, self::CONFIANCE_MAX_PARCELLE_PARTAGEE)
                        : $candidat->confiance,
                ]);
                $creees++;
            }
        }

        $bar->finish();
        $this->newLine();

        $this->info("Termine : {$creees} nouvelles propositions, {$dejaLiees} deja liees, {$dejaProposees} deja proposees, {$sansCandidat} parcelles sans compteur connu.");

        return self::SUCCESS;
    }
}
