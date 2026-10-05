<?php

namespace App\Console\Commands;

use App\Models\FicheSpancPyaVersion;
use App\Models\InstallationParcelleCourante;
use App\Models\Rapport;
use App\Support\Adressage;
use Illuminate\Console\Command;

/**
 * Convertit les fiches du deuxieme logiciel de diagnostic (fiche_spanc_pya_versions)
 * en Rapport, pour l'onglet "Rapports" de la fiche installation. Source
 * independante de Perigeo (voir CreerRapportsDepuisControlesPerigeoCommand/
 * CreerRapportsDepuisDispositifsSpancCommand), pas de recoupement attendu.
 *
 * parcelle_ids est deja resolu a l'import : une fiche multi-parcelles cree
 * un rapport sur chaque installation distincte touchee (en pratique une
 * seule, le bootstrap ayant traite ces parcelles comme une meme
 * installation — voir BootstrapInstallationsDepuisFichesSpancPyaCommand).
 */
class CreerRapportsDepuisFichesSpancPyaCommand extends Command
{
    protected $signature = 'installations:creer-rapports-depuis-fiches-spanc-pya';

    protected $description = 'Cree un Rapport par fiche du 2e logiciel de diagnostic reliee a une installation';

    /** @var array<string, string> */
    private const TYPE_CONTROLE = [
        'DIAGNOSTIC DE L EXISTANT' => 'diagnostic_initial',
        'DIAGNOSTIC DE BON FONCTIONNEMENT' => 'controle_periodique',
        'BONNE EXECUTION DES TRAVAUX' => 'controle_realisation',
        'DIAGNOSTIC DANS LE CADRE D UNE VENTE' => 'controle_vente',
        'CONCEPTION DU PROJET' => 'controle_conception',
    ];

    /** @var array<string, string> */
    private const CONCLUSION = [
        'CONFORME' => 'conforme',
        'NON CONFORME' => 'non_conforme',
    ];

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $fiches = FicheSpancPyaVersion::whereNotNull('parcelle_ids')->get();

        $this->info("{$fiches->count()} fiches a examiner.");

        $crees = 0;
        $dejaExistants = 0;
        $sansParcelle = 0;
        $sansInstallation = 0;
        $sansType = 0;

        $bar = $this->output->createProgressBar($fiches->count());
        $bar->start();

        foreach ($fiches as $fiche) {
            $bar->advance();

            $parcelleIds = $fiche->parcelle_ids ?: [];

            if ($parcelleIds === []) {
                $sansParcelle++;

                continue;
            }

            $installationIds = InstallationParcelleCourante::whereIn('parcelle_id', $parcelleIds)->pluck('installation_id')->unique();

            if ($installationIds->isEmpty()) {
                $sansInstallation++;

                continue;
            }

            $typeControle = self::TYPE_CONTROLE[Adressage::normaliser((string) $fiche->nature_dernier_controle)] ?? null;

            if (! $typeControle || ! $fiche->date_controle) {
                $sansType++;

                continue;
            }

            $conclusion = self::CONCLUSION[Adressage::normaliser((string) $fiche->avis)] ?? null;

            foreach ($installationIds as $installationId) {
                $existe = Rapport::where('installation_id', $installationId)
                    ->whereDate('date_controle', $fiche->date_controle)
                    ->where('type_controle', $typeControle)
                    ->exists();

                if ($existe) {
                    $dejaExistants++;

                    continue;
                }

                Rapport::create([
                    'installation_id' => $installationId,
                    'type_controle' => $typeControle,
                    'date_controle' => $fiche->date_controle,
                    'conclusion' => $conclusion,
                    'commentaire' => "Importe depuis le registre SPANC du 2e logiciel (fiche #{$fiche->id_source}), conformite : ".($fiche->conformite ?: 'non renseignee'),
                ]);
                $crees++;
            }
        }

        $bar->finish();
        $this->newLine();

        $this->info("Termine : {$crees} rapports crees, {$dejaExistants} deja existants, {$sansParcelle} sans parcelle resolue, {$sansInstallation} sans installation, {$sansType} sans type de controle exploitable.");

        return self::SUCCESS;
    }
}
