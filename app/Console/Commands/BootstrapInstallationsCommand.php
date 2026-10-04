<?php

namespace App\Console\Commands;

use App\Models\BatimentParcelleActuel;
use App\Models\BatimentVersion;
use App\Models\Installation;
use App\Models\InstallationBatimentCourant;
use App\Models\InstallationBatimentEvenement;
use App\Models\InstallationEtat;
use App\Models\InstallationParcelleEvenement;
use App\Models\ParcelleVersion;
use App\Support\RapprochementAnnexe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Une installation par maison rapprochée (pas par bâtiment, pas par
 * parcelle) : une annexe (type cadastral 02 — garage, grange, dépendance...)
 * n'a pas sa propre installation. Elle est rattachée à la maison la plus
 * proche sur la même parcelle (une parcelle peut porter plusieurs maisons
 * distinctes — hameau, corps de ferme). Si aucune maison n'est encore
 * connue sur sa parcelle, l'annexe reçoit tout de même sa propre
 * installation (orpheline) : mieux vaut la retrouver dans "à statuer" que la
 * perdre, elle pourra être rattachée plus tard via projections:rebuild +
 * une nouvelle exécution de cette commande.
 *
 * Type/statut ne sont qu'un point de départ : `non_determine` / `a_statuer`
 * (rien n'est présumé — la distinction AC/ANC sera déterminée via la base
 * SOGEDO), à corriger ensuite via un nouvel état.
 */
class BootstrapInstallationsCommand extends Command
{
    protected $signature = 'installations:bootstrap-depuis-batiments';

    protected $description = 'Cree une installation par maison rapprochee a une parcelle (les annexes rejoignent la maison la plus proche), non encore couvert par une installation existante';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $batimentsDejaLies = InstallationBatimentCourant::pluck('batiment_version_id')->flip();

        $rapprochements = BatimentParcelleActuel::all(['batiment_version_id', 'parcelle_version_id', 'confiance']);

        $this->info("{$rapprochements->count()} batiments rapproches a examiner.");

        $parcellesVersions = ParcelleVersion::whereIn('id', $rapprochements->pluck('parcelle_version_id')->unique())
            ->pluck('parcelle_id', 'id');

        $batiments = BatimentVersion::whereIn('id', $rapprochements->pluck('batiment_version_id')->unique())
            ->get(['id', 'type', 'centroide_lon', 'centroide_lat'])
            ->keyBy('id');

        // parcelle_id => [installation_id => [lon, lat]] des maisons deja courantes,
        // complete au fil de la boucle avec celles creees pendant cette meme execution.
        $maisonsParParcelle = DB::table('installation_parcelle_courante as ipc')
            ->join('installation_batiment_courant as ibc', 'ibc.installation_id', '=', 'ipc.installation_id')
            ->join('batiment_versions as bv', 'bv.id', '=', 'ibc.batiment_version_id')
            ->where('bv.type', '01')
            ->select('ipc.parcelle_id', 'ipc.installation_id', 'bv.centroide_lon', 'bv.centroide_lat')
            ->get()
            ->groupBy('parcelle_id')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($r) => [$r->installation_id => [$r->centroide_lon, $r->centroide_lat]])->all())
            ->all();

        // Les maisons d'abord : une annexe rapprochee dans la meme execution que
        // sa maison doit pouvoir la trouver dans $maisonsParParcelle.
        $rapprochements = $rapprochements->sortBy(
            fn ($r) => $batiments[$r->batiment_version_id]?->type === '02' ? 1 : 0
        )->values();

        $creees = 0;
        $dejaCouverts = 0;
        $rattachees = 0;

        $bar = $this->output->createProgressBar($rapprochements->count());
        $bar->start();

        DB::transaction(function () use ($rapprochements, $batimentsDejaLies, $parcellesVersions, $batiments, &$maisonsParParcelle, $bar, &$creees, &$dejaCouverts, &$rattachees) {
            foreach ($rapprochements as $rapprochement) {
                $bar->advance();

                if ($batimentsDejaLies->has($rapprochement->batiment_version_id)) {
                    $dejaCouverts++;

                    continue;
                }

                $parcelleId = $parcellesVersions[$rapprochement->parcelle_version_id] ?? null;
                $batiment = $batiments[$rapprochement->batiment_version_id] ?? null;

                if (! $parcelleId || ! $batiment) {
                    continue;
                }

                $maisonCible = $batiment->type === '02'
                    ? RapprochementAnnexe::plusProche($maisonsParParcelle[$parcelleId] ?? [], $batiment->centroide_lon, $batiment->centroide_lat)
                    : null;

                if ($maisonCible) {
                    InstallationBatimentEvenement::create([
                        'installation_id' => $maisonCible,
                        'batiment_version_id' => $rapprochement->batiment_version_id,
                        'action' => 'lier',
                        'source' => 'auto',
                        'confiance' => $rapprochement->confiance,
                        'motif' => 'Annexe rattachee automatiquement a la maison la plus proche sur la meme parcelle',
                    ]);

                    $rattachees++;

                    continue;
                }

                $installation = Installation::create([]);

                InstallationEtat::create([
                    'installation_id' => $installation->id,
                    'type' => 'non_determine',
                    'statut' => 'a_statuer',
                    'motif' => 'Bootstrap automatique depuis rapprochement cadastral (type et statut non determines, a confirmer notamment via la base SOGEDO pour la distinction AC/ANC)',
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

                if ($batiment->type === '01') {
                    $maisonsParParcelle[$parcelleId][$installation->id] = [$batiment->centroide_lon, $batiment->centroide_lat];
                }

                $creees++;
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Termine : {$creees} installations creees, {$rattachees} annexes rattachees a une maison existante, {$dejaCouverts} batiments deja couverts par une installation existante (ignores).");

        return self::SUCCESS;
    }
}
