<?php

namespace App\Console\Commands;

use App\Models\BatimentVersion;
use App\Models\ImportBatch;
use App\Support\Geometrie;
use Illuminate\Console\Command;

class ImporterBatimentsCommand extends Command
{
    protected $signature = 'cadastre:importer-batiments {fichier}';

    protected $description = 'Importe les batiments depuis un export GeoJSON.json.gz (cadastre.data.gouv.fr / BDTopo)';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $fichier = $this->argument('fichier');

        if (! is_file($fichier)) {
            $this->error("Fichier introuvable : {$fichier}");

            return self::FAILURE;
        }

        $batch = ImportBatch::create([
            'source' => 'cadastre_batiments',
            'fichier_origine' => $fichier,
            'statut' => 'en_cours',
            'demarre_le' => now(),
        ]);

        $this->info("Lecture de {$fichier}...");
        $contenu = json_decode(gzdecode(file_get_contents($fichier)), true, 512, JSON_THROW_ON_ERROR);
        $features = $contenu['features'];
        $total = count($features);
        $this->info("{$total} batiments trouves.");

        $lignes = [];
        $maintenant = now();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($features as $feature) {
            $props = $feature['properties'];
            $geometry = $feature['geometry'];

            [$centroideLon, $centroideLat] = Geometrie::centroideApproximatif($geometry);
            [$bboxMinLon, $bboxMaxLon, $bboxMinLat, $bboxMaxLat] = Geometrie::bbox($geometry);

            $lignes[] = [
                'cle_dedup' => hash('sha256', $props['commune'].json_encode($geometry)),
                'commune_insee' => $props['commune'],
                'type' => $props['type'] ?? null,
                'nom' => $props['nom'] ?? null,
                'geometry' => json_encode($geometry),
                'centroide_lon' => $centroideLon,
                'centroide_lat' => $centroideLat,
                'bbox_min_lon' => $bboxMinLon,
                'bbox_max_lon' => $bboxMaxLon,
                'bbox_min_lat' => $bboxMinLat,
                'bbox_max_lat' => $bboxMaxLat,
                'import_batch_id' => $batch->id,
                'created_at' => $maintenant,
            ];

            if (count($lignes) >= 500) {
                BatimentVersion::insert($lignes);
                $lignes = [];
            }

            $bar->advance();
        }

        if ($lignes !== []) {
            BatimentVersion::insert($lignes);
        }

        $bar->finish();
        $this->newLine();

        $batch->update(['statut' => 'termine', 'nombre_lignes' => $total, 'termine_le' => now()]);

        $this->info("Import termine : {$total} versions de batiments creees (batch #{$batch->id}).");

        return self::SUCCESS;
    }
}
