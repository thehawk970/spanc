<?php

namespace App\Console\Commands;

use App\Models\InstallationParcelleCourante;
use App\Models\InstallationProprietaireCourante;
use App\Models\ProprietaireParcelleActuel;
use App\Models\RapprochementPropose;
use Illuminate\Console\Command;

class ProposerProprietairesCommand extends Command
{
    protected $signature = 'installations:proposer-proprietaires';

    protected $description = "Propose de lier le proprietaire d'une parcelle a l'installation qui porte cette meme parcelle (file a valider dans Filament)";

    public function handle(): int
    {
        $liens = InstallationParcelleCourante::all(['installation_id', 'parcelle_id']);

        $this->info("{$liens->count()} liens installation-parcelle a examiner.");

        $creees = 0;
        $dejaLiees = 0;
        $dejaProposees = 0;
        $sansCandidat = 0;

        $bar = $this->output->createProgressBar($liens->count());
        $bar->start();

        foreach ($liens as $lien) {
            $bar->advance();

            $candidats = ProprietaireParcelleActuel::where('parcelle_id', $lien->parcelle_id)->get();

            if ($candidats->isEmpty()) {
                $sansCandidat++;

                continue;
            }

            foreach ($candidats as $candidat) {
                $dejaLie = InstallationProprietaireCourante::where('installation_id', $lien->installation_id)
                    ->where('proprietaire_version_id', $candidat->proprietaire_version_id)
                    ->exists();

                if ($dejaLie) {
                    $dejaLiees++;

                    continue;
                }

                $dejaPropose = RapprochementPropose::where('installation_id', $lien->installation_id)
                    ->where('type_cible', 'proprietaire')
                    ->where('cible_id', (string) $candidat->proprietaire_version_id)
                    ->exists();

                if ($dejaPropose) {
                    $dejaProposees++;

                    continue;
                }

                RapprochementPropose::create([
                    'installation_id' => $lien->installation_id,
                    'type_cible' => 'proprietaire',
                    'cible_id' => (string) $candidat->proprietaire_version_id,
                    'methode' => 'parcelle_commune',
                    'confiance' => $candidat->confiance,
                ]);
                $creees++;
            }
        }

        $bar->finish();
        $this->newLine();

        $this->info("Termine : {$creees} nouvelles propositions, {$dejaLiees} deja liees, {$dejaProposees} deja proposees, {$sansCandidat} parcelles sans proprietaire connu.");

        return self::SUCCESS;
    }
}
