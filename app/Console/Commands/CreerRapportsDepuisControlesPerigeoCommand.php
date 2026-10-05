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
 * Convertit l'historique de controle Perigeo (controle_versions) en
 * Rapport, pour qu'il apparaisse dans l'onglet "Rapports" de la fiche
 * installation (App\Filament\Resources\Installations\RelationManagers\RapportsRelationManager,
 * jusqu'ici jamais alimenté que manuellement).
 *
 * Seuls les controles relies a un dispositif connu sont traites ici (la
 * parcelle se resout via le dispositif, controle_versions n'en a pas en
 * propre) — voir CreerRapportsDepuisDispositifsSpancCommand pour les
 * dispositifs sans aucun controle_versions correspondant (repli sur le
 * resume du dernier controle).
 */
class CreerRapportsDepuisControlesPerigeoCommand extends Command
{
    protected $signature = 'installations:creer-rapports-depuis-controles-perigeo';

    protected $description = "Cree un Rapport par controle Perigeo (controle_versions) relie a une installation, pour l'onglet Rapports";

    /** @var array<string, string> */
    private const TYPE_CONTROLE = [
        'VERIFICATION DE L EXECUTION SUITE A UN PROJET DE CONCEPTION' => 'controle_realisation',
        'CONTRE VISITE SUITE A UNE DEMANDE DE MODIFICATION D EXECUTION' => 'controle_realisation',
        'PERIODIQUE' => 'controle_periodique',
        'DIAGNOSTIC' => 'diagnostic_initial',
        'VENTE' => 'controle_vente',
    ];

    /** @var array<string, string> */
    private const CONCLUSION = [
        'CONFORME' => 'conforme',
        'CONFORME SOUS RESERVE' => 'avec_reserves',
        'NON CONFORME' => 'non_conforme',
    ];

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $parcelleIdParSectionNumero = ParcelleVersion::query()
            ->get(['parcelle_id', 'commune_insee', 'section', 'numero'])
            ->mapWithKeys(fn ($p) => ["{$p->commune_insee}|{$p->section}|{$p->numero}" => $p->parcelle_id]);

        $dispositifParReference = DispositifSpancVersion::whereNotNull('section')->whereNotNull('numero')
            ->get(['reference_dossier', 'code_insee', 'section', 'numero'])
            ->keyBy('reference_dossier');

        $controles = ControleVersion::whereNotNull('reference_dossier_liee')->get();

        $this->info("{$controles->count()} controles a examiner.");

        $crees = 0;
        $dejaExistants = 0;
        $sansDispositif = 0;
        $sansParcelle = 0;
        $sansInstallation = 0;
        $sansType = 0;

        $bar = $this->output->createProgressBar($controles->count());
        $bar->start();

        foreach ($controles as $controle) {
            $bar->advance();

            $dispositif = $dispositifParReference[$controle->reference_dossier_liee] ?? null;

            if (! $dispositif) {
                $sansDispositif++;

                continue;
            }

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

            $typeControle = self::TYPE_CONTROLE[Adressage::normaliser((string) $controle->cadre_ou_type)] ?? null;

            if (! $typeControle || ! $controle->date_visite) {
                $sansType++;

                continue;
            }

            $conclusion = self::CONCLUSION[Adressage::normaliser((string) $controle->avis)] ?? null;

            foreach ($installationIds as $installationId) {
                $existe = Rapport::where('installation_id', $installationId)
                    ->whereDate('date_controle', $controle->date_visite)
                    ->where('type_controle', $typeControle)
                    ->exists();

                if ($existe) {
                    $dejaExistants++;

                    continue;
                }

                Rapport::create([
                    'installation_id' => $installationId,
                    'type_controle' => $typeControle,
                    'date_controle' => $controle->date_visite,
                    'conclusion' => $conclusion,
                    'commentaire' => "Importe depuis le registre SPANC Perigeo (controle {$controle->reference_controle}), technicien : ".($controle->technicien ?: 'non renseigne'),
                ]);
                $crees++;
            }
        }

        $bar->finish();
        $this->newLine();

        $this->info("Termine : {$crees} rapports crees, {$dejaExistants} deja existants, {$sansDispositif} sans dispositif relie, {$sansParcelle} sans parcelle resolue, {$sansInstallation} sans installation, {$sansType} sans type de controle exploitable.");

        return self::SUCCESS;
    }
}
