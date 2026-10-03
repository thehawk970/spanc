<?php

namespace App\Console\Commands;

use App\Models\Installation;
use App\Models\InstallationBatimentCourant;
use App\Models\InstallationBatimentEvenement;
use App\Models\InstallationEtat;
use App\Models\InstallationParcelleCourante;
use App\Models\InstallationParcelleEvenement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Correction ponctuelle (purement additive, rien n'est supprimé ni modifié
 * en place) du premier bootstrap, qui groupait par parcelle au lieu d'un
 * bâtiment par installation :
 *  1. Aligne le statut sur 'a_statuer' (nouvel état, l'ancien reste dans
 *     l'historique).
 *  2. Pour les installations qui portent plusieurs bâtiments, délie tous
 *     sauf le premier et leur crée chacun leur propre installation.
 */
class CorrigerBootstrapCommand extends Command
{
    protected $signature = 'installations:corriger-bootstrap-un-batiment';

    protected $description = "Correction ponctuelle du premier bootstrap : separe en une installation par batiment et aligne le statut sur 'a_statuer'";

    public function handle(): int
    {
        $installationsBootstrap = InstallationEtat::where('motif', 'like', 'Bootstrap automatique depuis rapprochement cadastral (batiment%')
            ->pluck('installation_id');

        $this->info("{$installationsBootstrap->count()} installations issues du premier bootstrap a corriger.");

        $statutsCorriges = 0;
        $separations = 0;

        $bar = $this->output->createProgressBar($installationsBootstrap->count());
        $bar->start();

        DB::transaction(function () use ($installationsBootstrap, $bar, &$statutsCorriges, &$separations) {
            foreach ($installationsBootstrap as $installationId) {
                $bar->advance();

                InstallationEtat::create([
                    'installation_id' => $installationId,
                    'type' => 'non_determine',
                    'statut' => 'a_statuer',
                    'motif' => "Correction : le premier bootstrap avait mis 'a_controler' par erreur ; type/statut restent a confirmer (notamment via la base SOGEDO)",
                ]);
                $statutsCorriges++;

                $liens = InstallationBatimentCourant::where('installation_id', $installationId)
                    ->orderBy('id')
                    ->get();

                if ($liens->count() <= 1) {
                    continue;
                }

                $parcelleId = InstallationParcelleCourante::where('installation_id', $installationId)->value('parcelle_id');

                foreach ($liens->skip(1) as $lien) {
                    InstallationBatimentEvenement::create([
                        'installation_id' => $installationId,
                        'batiment_version_id' => $lien->batiment_version_id,
                        'action' => 'delier',
                        'source' => 'auto',
                        'motif' => 'Separation : un batiment par installation plutot que groupe par parcelle',
                    ]);

                    $nouvelle = Installation::create([]);

                    InstallationEtat::create([
                        'installation_id' => $nouvelle->id,
                        'type' => 'non_determine',
                        'statut' => 'a_statuer',
                        'motif' => "Separee de l'installation {$installationId} (le bootstrap par parcelle regroupait plusieurs batiments)",
                    ]);

                    if ($parcelleId) {
                        InstallationParcelleEvenement::create([
                            'installation_id' => $nouvelle->id,
                            'parcelle_id' => $parcelleId,
                            'action' => 'lier',
                            'source' => 'auto',
                            'confiance' => 1.0,
                        ]);
                    }

                    InstallationBatimentEvenement::create([
                        'installation_id' => $nouvelle->id,
                        'batiment_version_id' => $lien->batiment_version_id,
                        'action' => 'lier',
                        'source' => 'auto',
                        'confiance' => $lien->confiance,
                    ]);

                    $separations++;
                }
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Termine : {$statutsCorriges} statuts corriges, {$separations} nouvelles installations issues d'une separation.");

        return self::SUCCESS;
    }
}
