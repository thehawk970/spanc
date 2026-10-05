<?php

namespace App\Console\Commands;

use App\Models\FicheSpancPyaVersion;
use App\Models\Installation;
use App\Models\InstallationEtat;
use App\Models\InstallationParcelleCourante;
use App\Models\InstallationParcelleEvenement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Quatrième étage du bootstrap, après la BAN, le fichier logement, puis les
 * dispositifs SPANC Perigeo (voir
 * BootstrapInstallationsDepuisDispositifsSpancCommand) : le deuxième
 * logiciel de diagnostic (pya) peut connaître des parcelles qu'aucune des
 * sources precedentes n'a couvertes.
 *
 * Une fiche peut lister plusieurs parcelles (voir
 * ImporterFichesSpancPyaCommand) : traitee comme une seule installation
 * couvrant toutes ces parcelles, jamais une par parcelle — coherent avec le
 * reste du projet (une installation peut deja porter plusieurs parcelles).
 * Si une partie seulement des parcelles de la fiche est deja couverte (par
 * une autre installation), la fiche est ignoree plutot que de deviner a
 * quelle installation existante rattacher le reste : cas rare, laisse a la
 * revue manuelle.
 */
class BootstrapInstallationsDepuisFichesSpancPyaCommand extends Command
{
    protected $signature = 'installations:bootstrap-depuis-fiches-spanc-pya';

    protected $description = "Cree une installation pour chaque fiche SPANC (2e logiciel) dont aucune parcelle n'est encore couverte";

    public function handle(): int
    {
        $parcellesCouvertes = InstallationParcelleCourante::pluck('parcelle_id')->unique()->mapWithKeys(fn ($id) => [$id => true]);

        $fiches = FicheSpancPyaVersion::whereNotNull('parcelle_ids')->get(['parcelle_ids']);

        $this->info("{$fiches->count()} fiches a examiner.");

        $creees = 0;
        $dejaCouvertes = 0;
        $partiellementCouvertes = 0;
        $sansParcelle = 0;

        $bar = $this->output->createProgressBar($fiches->count());
        $bar->start();

        DB::transaction(function () use ($fiches, &$parcellesCouvertes, $bar, &$creees, &$dejaCouvertes, &$partiellementCouvertes, &$sansParcelle) {
            foreach ($fiches as $fiche) {
                $bar->advance();

                $ids = $fiche->parcelle_ids ?: [];

                if ($ids === []) {
                    $sansParcelle++;

                    continue;
                }

                $couvertes = array_filter($ids, fn ($id) => $parcellesCouvertes->has($id));

                if (count($couvertes) === count($ids)) {
                    $dejaCouvertes++;

                    continue;
                }

                if ($couvertes !== []) {
                    $partiellementCouvertes++;

                    continue;
                }

                $installation = Installation::create([]);

                InstallationEtat::create([
                    'installation_id' => $installation->id,
                    'type' => 'non_determine',
                    'statut' => 'a_statuer',
                    'motif' => 'Bootstrap automatique depuis fiche SPANC (2e logiciel, diagnostic-pya)',
                ]);

                foreach ($ids as $parcelleId) {
                    InstallationParcelleEvenement::create([
                        'installation_id' => $installation->id,
                        'parcelle_id' => $parcelleId,
                        'action' => 'lier',
                        'source' => 'auto',
                        'confiance' => 1.0,
                        'motif' => 'Parcelle resolue depuis le registre SPANC (2e logiciel)',
                    ]);
                    $parcellesCouvertes[$parcelleId] = true;
                }

                $creees++;
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Termine : {$creees} installations creees, {$dejaCouvertes} fiches deja couvertes (ignorees), {$partiellementCouvertes} partiellement couvertes (ignorees, revue manuelle), {$sansParcelle} sans parcelle resolue.");

        return self::SUCCESS;
    }
}
