<?php

namespace App\Console\Commands;

use App\Models\ControleVersion;
use App\Models\DispositifSpancVersion;
use App\Models\InstallationParcelleCourante;
use App\Models\ParcelleVersion;
use App\Models\Rapport;
use App\Support\Adressage;
use Illuminate\Console\Command;

/**
 * Complément à CreerRapportsDepuisControlesPerigeoCommand : un dispositif
 * SPANC (dispositif_spanc_versions) sans aucun controle_versions
 * correspondant (99/607) n'aurait sinon aucun rapport — son resume de
 * dernier controle est utilise ici en repli, pour ne pas perdre
 * l'information.
 */
class CreerRapportsDepuisDispositifsSpancCommand extends Command
{
    protected $signature = 'installations:creer-rapports-depuis-dispositifs-spanc';

    protected $description = "Cree un Rapport depuis le resume du dernier controle d'un dispositif SPANC sans controle_versions correspondant";

    /** @var array<string, string> */
    private const TYPE_CONTROLE = [
        'BONNE EXECUTION' => 'controle_realisation',
        'PERIODIQUE' => 'controle_periodique',
        'DIAGNOSTIC' => 'diagnostic_initial',
        'VENTE' => 'controle_vente',
    ];

    /** @var array<string, string> */
    private const CONCLUSION = [
        'CONFORME' => 'conforme',
        'CONFORME SOUS RESERVE' => 'avec_reserves',
        'NON CONFORME' => 'non_conforme',
        'INSTALLATION NON CONFORME' => 'non_conforme',
        'INSTALLATION NE PRESENTANT PAS DE DEFAUTS' => 'conforme',
        'INSTALLATION PRESENTANT DES DEFAUTS D ENTRETIEN OU UNE USURE DE L UN DE SES ELEMENTS CONSTITUTIFS' => 'avec_reserves',
    ];

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $referencesAvecControle = ControleVersion::whereNotNull('reference_dossier_liee')->pluck('reference_dossier_liee')->unique();

        $parcelleIdParSectionNumero = ParcelleVersion::query()
            ->get(['parcelle_id', 'commune_insee', 'section', 'numero'])
            ->mapWithKeys(fn ($p) => ["{$p->commune_insee}|{$p->section}|{$p->numero}" => $p->parcelle_id]);

        $dispositifs = DispositifSpancVersion::whereNotNull('section')
            ->whereNotNull('numero')
            ->whereNotIn('reference_dossier', $referencesAvecControle)
            ->get();

        $this->info("{$dispositifs->count()} dispositifs sans controle_versions a examiner.");

        $crees = 0;
        $dejaExistants = 0;
        $sansParcelle = 0;
        $sansInstallation = 0;
        $sansType = 0;

        $bar = $this->output->createProgressBar($dispositifs->count());
        $bar->start();

        foreach ($dispositifs as $dispositif) {
            $bar->advance();

            $parcelleId = $parcelleIdParSectionNumero["{$dispositif->code_insee}|{$dispositif->section}|{$dispositif->numero}"] ?? null;

            if (! $parcelleId) {
                $sansParcelle++;

                continue;
            }

            $installationIds = InstallationParcelleCourante::where('parcelle_id', $parcelleId)->pluck('installation_id');

            if ($installationIds->isEmpty()) {
                $sansInstallation++;

                continue;
            }

            $typeControle = self::TYPE_CONTROLE[Adressage::normaliser((string) $dispositif->nature_dernier_controle)] ?? null;

            if (! $typeControle || ! $dispositif->date_derniere_visite) {
                $sansType++;

                continue;
            }

            $conclusion = self::CONCLUSION[Adressage::normaliser((string) $dispositif->avis_dernier_controle)] ?? null;

            foreach ($installationIds as $installationId) {
                $existe = Rapport::where('installation_id', $installationId)
                    ->whereDate('date_controle', $dispositif->date_derniere_visite)
                    ->where('type_controle', $typeControle)
                    ->exists();

                if ($existe) {
                    $dejaExistants++;

                    continue;
                }

                Rapport::create([
                    'installation_id' => $installationId,
                    'type_controle' => $typeControle,
                    'date_controle' => $dispositif->date_derniere_visite,
                    'conclusion' => $conclusion,
                    'commentaire' => "Importe depuis le registre SPANC Perigeo (dossier {$dispositif->reference_dossier}, resume sans detail d'evenement), technicien : ".($dispositif->technicien ?: 'non renseigne'),
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
