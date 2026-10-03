<?php

namespace App\Console\Commands;

use App\Models\BatimentParcelleActuel;
use App\Models\Installation;
use App\Models\InstallationBatimentCourant;
use App\Models\InstallationBatimentEvenement;
use App\Models\InstallationEtat;
use App\Models\InstallationParcelleEvenement;
use App\Models\ParcelleVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Une installation par bâtiment rapproché (pas par parcelle) : deux
 * bâtiments sur la même parcelle (maison + annexe) ne partagent PAS
 * automatiquement une installation — ce serait supposer qu'ils partagent le
 * même système d'assainissement sans preuve de terrain. Le rapprochement
 * (fusionner deux installations) reste une décision humaine ultérieure.
 *
 * Type/statut ne sont qu'un point de départ : `non_determine` / `a_statuer`
 * (rien n'est présumé — la distinction AC/ANC sera déterminée via la base
 * SOGEDO), à corriger ensuite via un nouvel état.
 */
class BootstrapInstallationsCommand extends Command
{
    protected $signature = 'installations:bootstrap-depuis-batiments';

    protected $description = "Cree une installation par batiment rapproche a une parcelle, non encore couvert par une installation existante";

    public function handle(): int
    {
        $batimentsDejaLies = InstallationBatimentCourant::pluck('batiment_version_id')->flip();

        $rapprochements = BatimentParcelleActuel::all(['batiment_version_id', 'parcelle_version_id', 'confiance']);

        $this->info("{$rapprochements->count()} batiments rapproches a examiner.");

        $parcellesVersions = ParcelleVersion::whereIn('id', $rapprochements->pluck('parcelle_version_id')->unique())
            ->pluck('parcelle_id', 'id');

        $creees = 0;
        $dejaCouverts = 0;

        $bar = $this->output->createProgressBar($rapprochements->count());
        $bar->start();

        DB::transaction(function () use ($rapprochements, $batimentsDejaLies, $parcellesVersions, $bar, &$creees, &$dejaCouverts) {
            foreach ($rapprochements as $rapprochement) {
                $bar->advance();

                if ($batimentsDejaLies->has($rapprochement->batiment_version_id)) {
                    $dejaCouverts++;

                    continue;
                }

                $parcelleId = $parcellesVersions[$rapprochement->parcelle_version_id] ?? null;

                if (! $parcelleId) {
                    continue;
                }

                $installation = Installation::create([]);

                InstallationEtat::create([
                    'installation_id' => $installation->id,
                    'type' => 'non_determine',
                    'statut' => 'a_statuer',
                    'motif' => "Bootstrap automatique depuis rapprochement cadastral (type et statut non determines, a confirmer notamment via la base SOGEDO pour la distinction AC/ANC)",
                ]);

                InstallationParcelleEvenement::create([
                    'installation_id' => $installation->id,
                    'parcelle_id' => $parcelleId,
                    'action' => 'lier',
                    'source' => 'auto',
                    'confiance' => 1.0,
                ]);

                InstallationBatimentEvenement::create([
                    'installation_id' => $installation->id,
                    'batiment_version_id' => $rapprochement->batiment_version_id,
                    'action' => 'lier',
                    'source' => 'auto',
                    'confiance' => $rapprochement->confiance,
                ]);

                $creees++;
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Termine : {$creees} installations creees, {$dejaCouverts} batiments deja couverts par une installation existante (ignores).");

        return self::SUCCESS;
    }
}
