<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Models\ParcelleVersion;
use Illuminate\Console\Command;

class ImporterParcellesCommand extends Command
{
    protected $signature = 'cadastre:importer-parcelles {fichier}';

    protected $description = 'Importe les parcelles cadastrales depuis un export GeoJSON.json.gz (cadastre.data.gouv.fr)';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $fichier = $this->argument('fichier');

        if (! is_file($fichier)) {
            $this->error("Fichier introuvable : {$fichier}");

            return self::FAILURE;
        }

        $batch = ImportBatch::create([
            'source' => 'cadastre_parcelles',
            'fichier_origine' => $fichier,
            'statut' => 'en_cours',
            'demarre_le' => now(),
        ]);

        $this->info("Lecture de {$fichier}...");
        $contenu = json_decode(gzdecode(file_get_contents($fichier)), true, 512, JSON_THROW_ON_ERROR);
        $features = $contenu['features'];
        $total = count($features);
        $this->info("{$total} parcelles trouvees.");

        $lignes = [];
        $maintenant = now();
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($features as $feature) {
            $props = $feature['properties'];

            $lignes[] = [
                'parcelle_id' => $props['id'],
                'commune_insee' => $props['commune'],
                'section' => $props['section'],
                'numero' => $props['numero'],
                'geometry' => json_encode($feature['geometry']),
                'surface_m2' => $props['contenance'] ?? null,
                'proprietes_brutes' => json_encode($props),
                'import_batch_id' => $batch->id,
                'created_at' => $maintenant,
            ];

            if (count($lignes) >= 500) {
                ParcelleVersion::insert($lignes);
                $lignes = [];
            }

            $bar->advance();
        }

        if ($lignes !== []) {
            ParcelleVersion::insert($lignes);
        }

        $bar->finish();
        $this->newLine();

        $batch->update(['statut' => 'termine', 'nombre_lignes' => $total, 'termine_le' => now()]);

        $this->info("Import termine : {$total} versions de parcelles creees (batch #{$batch->id}).");

        return self::SUCCESS;
    }
}
