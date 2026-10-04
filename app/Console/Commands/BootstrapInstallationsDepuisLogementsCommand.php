<?php

namespace App\Console\Commands;

use App\Models\Installation;
use App\Models\InstallationEtat;
use App\Models\InstallationParcelleCourante;
use App\Models\InstallationParcelleEvenement;
use App\Models\LogementHorsAssCollVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deuxième étage du bootstrap, après la BAN (voir
 * BootstrapInstallationsDepuisAdressesCommand) : la BAN ne couvre que ~73%
 * des adresses et son propriétaire associé (BAN pro) ne couvre que Pays de
 * Belvès (le fichier source n'a jamais eu les 19 autres communes de
 * l'EPCI). L'export fiscal/cadastral des logements hors assainissement
 * collectif, lui, couvre bien les 20 communes avec un lien parcelle exact
 * (idu) — utilisé ici pour créer les installations qu'aucune adresse BAN
 * n'a encore créées sur leur parcelle.
 *
 * Volontairement simple : pas de rattachement bâtiment ici (contrairement
 * au bootstrap BAN), le fichier logement n'a pas de centroïde de bâtiment
 * individuel exploitable au même niveau de précision. Le rattachement
 * bâtiment manuel (ViewBatimentVersion::creer_installation) reste
 * disponible pour l'enrichir sur le terrain.
 */
class BootstrapInstallationsDepuisLogementsCommand extends Command
{
    protected $signature = 'installations:bootstrap-depuis-logements';

    protected $description = 'Cree une installation pour chaque logement (export hors assainissement collectif) dont la parcelle ne porte encore aucune installation';

    public function handle(): int
    {
        $parcellesCouvertes = InstallationParcelleCourante::pluck('parcelle_id')->unique()->mapWithKeys(fn ($id) => [$id => true]);

        $logements = LogementHorsAssCollVersion::all(['parcelle_id']);

        $this->info("{$logements->count()} logements a examiner.");

        $creees = 0;
        $dejaCouvertes = 0;

        $bar = $this->output->createProgressBar($logements->count());
        $bar->start();

        DB::transaction(function () use ($logements, &$parcellesCouvertes, $bar, &$creees, &$dejaCouvertes) {
            foreach ($logements as $logement) {
                $bar->advance();

                if ($parcellesCouvertes->has($logement->parcelle_id)) {
                    $dejaCouvertes++;

                    continue;
                }

                $installation = Installation::create([]);

                InstallationEtat::create([
                    'installation_id' => $installation->id,
                    'type' => 'non_determine',
                    'statut' => 'a_statuer',
                    'motif' => 'Bootstrap automatique depuis logement hors assainissement collectif',
                ]);

                InstallationParcelleEvenement::create([
                    'installation_id' => $installation->id,
                    'parcelle_id' => $logement->parcelle_id,
                    'action' => 'lier',
                    'source' => 'auto',
                    'confiance' => 1.0,
                    'motif' => 'Parcelle exacte (idu) du fichier logement hors assainissement collectif',
                ]);

                $parcellesCouvertes[$logement->parcelle_id] = true;
                $creees++;
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Termine : {$creees} installations creees, {$dejaCouvertes} parcelles deja couvertes (ignorees).");

        return self::SUCCESS;
    }
}
