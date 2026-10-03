<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Models\ProprietaireVersion;
use Illuminate\Console\Command;

class ImporterProprietairesCommand extends Command
{
    protected $signature = 'proprietaires:importer {fichier}';

    protected $description = 'Importe un export de propriétaires (CSV point-adresse, type BAN pro) dans le référentiel versionné';

    public function handle(): int
    {
        $fichier = $this->argument('fichier');

        if (! is_file($fichier)) {
            $this->error("Fichier introuvable : {$fichier}");

            return self::FAILURE;
        }

        $batch = ImportBatch::create([
            'source' => 'proprietaires_csv',
            'fichier_origine' => $fichier,
            'statut' => 'en_cours',
            'demarre_le' => now(),
        ]);

        $handle = fopen($fichier, 'r');

        // Retire le BOM UTF-8 eventuel en tete de fichier.
        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $entetes = fgetcsv($handle, 0, ';');
        $total = 0;
        $ignorees = 0;
        $lignes = [];
        $maintenant = now();

        while (($ligne = fgetcsv($handle, 0, ';')) !== false) {
            if (count($ligne) !== count($entetes)) {
                $ignorees++;

                continue;
            }

            $props = array_combine($entetes, $ligne);
            $nomComplet = trim($props['Nom'] ?? '');

            if ($nomComplet === '') {
                $ignorees++;

                continue;
            }

            [$nom, $prenom] = str_contains($nomComplet, '/')
                ? explode('/', $nomComplet, 2)
                : [$nomComplet, null];

            $lignes[] = [
                'nom' => trim($nom),
                'prenom' => $prenom !== null ? trim($prenom) : null,
                'contact' => null,
                'proprietes_brutes' => json_encode($props, JSON_UNESCAPED_UNICODE),
                'import_batch_id' => $batch->id,
                'created_at' => $maintenant,
            ];
            $total++;

            if (count($lignes) >= 500) {
                ProprietaireVersion::insert($lignes);
                $lignes = [];
            }
        }

        if ($lignes !== []) {
            ProprietaireVersion::insert($lignes);
        }

        fclose($handle);

        $batch->update([
            'statut' => 'termine',
            'nombre_lignes' => $total,
            'termine_le' => now(),
            'commentaire' => $ignorees > 0 ? "{$ignorees} ligne(s) ignoree(s) (malformees ou sans nom)" : null,
        ]);

        $this->info("Import termine : {$total} proprietaires importes, {$ignorees} ligne(s) ignoree(s) (batch #{$batch->id}).");

        return self::SUCCESS;
    }
}
