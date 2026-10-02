<?php

namespace App\Console\Commands;

use App\Models\BatimentParcelleRapprochement;
use App\Models\BatimentVersion;
use App\Models\ImportBatch;
use App\Models\ParcelleVersion;
use App\Support\Geometrie;
use Generator;
use Illuminate\Console\Command;

class RapprocherBatimentsParcellesCommand extends Command
{
    /** ~100m : assez fin pour que chaque cellule ne contienne qu'une poignee de parcelles candidates. */
    private const TAILLE_CELLULE = 0.001;

    protected $signature = 'cadastre:rapprocher-batiments
        {--batch-parcelles= : import_batch_id des parcelles a utiliser (par defaut : le plus recent)}
        {--batch-batiments= : import_batch_id des batiments a utiliser (par defaut : le plus recent)}';

    protected $description = 'Rapproche chaque batiment de sa parcelle via un index spatial en grille (centroide dans polygone)';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $batchParcellesId = $this->option('batch-parcelles')
            ?? ImportBatch::where('source', 'cadastre_parcelles')->latest('id')->value('id');
        $batchBatimentsId = $this->option('batch-batiments')
            ?? ImportBatch::where('source', 'cadastre_batiments')->latest('id')->value('id');

        if (! $batchParcellesId || ! $batchBatimentsId) {
            $this->error('Aucun import de parcelles ou de batiments trouve.');

            return self::FAILURE;
        }

        $this->info("Parcelles : batch #{$batchParcellesId} — Batiments : batch #{$batchBatimentsId}");

        $batchRapprochement = ImportBatch::create([
            'source' => 'rapprochement_geometrique',
            'statut' => 'en_cours',
            'demarre_le' => now(),
        ]);

        $this->info("Construction de l'index spatial des parcelles...");
        $parcelles = ParcelleVersion::where('import_batch_id', $batchParcellesId)->get(['id', 'geometry']);

        $geometries = [];
        $grille = [];

        $bar = $this->output->createProgressBar($parcelles->count());
        foreach ($parcelles as $parcelle) {
            $geom = $parcelle->geometry; // deja decode par le cast 'array' du modele
            $geometries[$parcelle->id] = $geom;

            [$minLon, $maxLon, $minLat, $maxLat] = Geometrie::bbox($geom);

            foreach ($this->cellulesPourBbox($minLon, $maxLon, $minLat, $maxLat) as $cellule) {
                $grille[$cellule][] = $parcelle->id;
            }

            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $this->info('Rapprochement des batiments...');
        $batiments = BatimentVersion::where('import_batch_id', $batchBatimentsId)
            ->get(['id', 'centroide_lon', 'centroide_lat']);

        $trouves = 0;
        $nonTrouves = 0;
        $lignes = [];

        $bar = $this->output->createProgressBar($batiments->count());
        foreach ($batiments as $batiment) {
            $candidats = array_unique($this->candidatsAutourDe($grille, $batiment->centroide_lon, $batiment->centroide_lat));

            $parcelleTrouvee = null;
            foreach ($candidats as $parcelleId) {
                if (Geometrie::pointDansGeometrie($batiment->centroide_lon, $batiment->centroide_lat, $geometries[$parcelleId])) {
                    $parcelleTrouvee = $parcelleId;
                    break;
                }
            }

            if ($parcelleTrouvee !== null) {
                $trouves++;
                $lignes[] = [
                    'batiment_version_id' => $batiment->id,
                    'parcelle_version_id' => $parcelleTrouvee,
                    'methode' => 'centroide_dans_polygone',
                    'confiance' => 1.0,
                    'import_batch_id' => $batchRapprochement->id,
                ];
            } else {
                $nonTrouves++;
            }

            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        // create() (pas insert() en masse) pour declencher l'observer qui
        // maintient la projection batiment_parcelle_actuel.
        foreach ($lignes as $ligne) {
            BatimentParcelleRapprochement::create($ligne);
        }

        $batchRapprochement->update([
            'statut' => 'termine',
            'nombre_lignes' => $trouves,
            'termine_le' => now(),
            'commentaire' => "{$trouves} rapproches, {$nonTrouves} sans correspondance",
        ]);

        $this->info("Termine : {$trouves} batiments rapproches, {$nonTrouves} sans parcelle correspondante.");

        return self::SUCCESS;
    }

    private function cellulesPourBbox(float $minLon, float $maxLon, float $minLat, float $maxLat): Generator
    {
        $iMin = (int) floor($minLon / self::TAILLE_CELLULE);
        $iMax = (int) floor($maxLon / self::TAILLE_CELLULE);
        $jMin = (int) floor($minLat / self::TAILLE_CELLULE);
        $jMax = (int) floor($maxLat / self::TAILLE_CELLULE);

        for ($i = $iMin; $i <= $iMax; $i++) {
            for ($j = $jMin; $j <= $jMax; $j++) {
                yield "{$i}_{$j}";
            }
        }
    }

    private function candidatsAutourDe(array $grille, float $lon, float $lat): array
    {
        $i = (int) floor($lon / self::TAILLE_CELLULE);
        $j = (int) floor($lat / self::TAILLE_CELLULE);

        $candidats = [];
        for ($di = -1; $di <= 1; $di++) {
            for ($dj = -1; $dj <= 1; $dj++) {
                $cellule = ($i + $di).'_'.($j + $dj);
                if (isset($grille[$cellule])) {
                    $candidats = array_merge($candidats, $grille[$cellule]);
                }
            }
        }

        return $candidats;
    }
}
