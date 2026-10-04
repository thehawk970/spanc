<?php

namespace App\Console\Commands;

use App\Models\DispositifSpancVersion;
use App\Models\Installation;
use App\Models\InstallationEtat;
use App\Models\InstallationParcelleCourante;
use App\Models\InstallationParcelleEvenement;
use App\Models\ParcelleVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Troisième étage du bootstrap, après la BAN puis le fichier logement (voir
 * BootstrapInstallationsDepuisLogementsCommand) : le registre SPANC
 * historique (Perigeo) peut connaître un dispositif sur une parcelle
 * qu'aucune des deux sources precedentes n'a couverte. Parcelle retrouvee
 * via (code_insee, section, numero) — voir ImporterDispositifsSpancCommand
 * pour pourquoi ce n'est pas une reconstruction directe de parcelle_id.
 *
 * Chaque ligne de dispositif_spanc_versions EST par definition un
 * dispositif non collectif suivi par le SPANC : voir
 * DeterminerEtatDepuisDispositifsSpancCommand pour la mise a jour du type
 * et statut sur les installations (nouvelles ou deja existantes) a partir
 * de l'avis de controle reel.
 */
class BootstrapInstallationsDepuisDispositifsSpancCommand extends Command
{
    protected $signature = 'installations:bootstrap-depuis-dispositifs-spanc';

    protected $description = 'Cree une installation pour chaque dispositif SPANC (Perigeo) dont la parcelle ne porte encore aucune installation';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $parcellesCouvertes = InstallationParcelleCourante::pluck('parcelle_id')->unique()->mapWithKeys(fn ($id) => [$id => true]);

        $parcelleIdParSectionNumero = ParcelleVersion::query()
            ->get(['parcelle_id', 'commune_insee', 'section', 'numero'])
            ->mapWithKeys(fn ($p) => ["{$p->commune_insee}|{$p->section}|{$p->numero}" => $p->parcelle_id]);

        $dispositifs = DispositifSpancVersion::whereNotNull('section')->whereNotNull('numero')->get(['code_insee', 'section', 'numero']);

        $this->info("{$dispositifs->count()} dispositifs a examiner.");

        $creees = 0;
        $dejaCouvertes = 0;
        $sansParcelle = 0;

        $bar = $this->output->createProgressBar($dispositifs->count());
        $bar->start();

        DB::transaction(function () use ($dispositifs, $parcelleIdParSectionNumero, &$parcellesCouvertes, $bar, &$creees, &$dejaCouvertes, &$sansParcelle) {
            foreach ($dispositifs as $dispositif) {
                $bar->advance();

                $parcelleId = $parcelleIdParSectionNumero["{$dispositif->code_insee}|{$dispositif->section}|{$dispositif->numero}"] ?? null;

                if ($parcelleId === null) {
                    $sansParcelle++;

                    continue;
                }

                if ($parcellesCouvertes->has($parcelleId)) {
                    $dejaCouvertes++;

                    continue;
                }

                $installation = Installation::create([]);

                InstallationEtat::create([
                    'installation_id' => $installation->id,
                    'type' => 'non_determine',
                    'statut' => 'a_statuer',
                    'motif' => 'Bootstrap automatique depuis dispositif SPANC (Perigeo)',
                ]);

                InstallationParcelleEvenement::create([
                    'installation_id' => $installation->id,
                    'parcelle_id' => $parcelleId,
                    'action' => 'lier',
                    'source' => 'auto',
                    'confiance' => 1.0,
                    'motif' => 'Parcelle exacte (section/numero) du registre SPANC Perigeo',
                ]);

                $parcellesCouvertes[$parcelleId] = true;
                $creees++;
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Termine : {$creees} installations creees, {$dejaCouvertes} parcelles deja couvertes (ignorees), {$sansParcelle} sans parcelle correspondante en base.");

        return self::SUCCESS;
    }
}
