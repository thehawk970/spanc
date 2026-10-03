<?php

namespace App\Console\Commands;

use App\Models\AdresseVersion;
use App\Models\ImportBatch;
use App\Models\ParcelleVersion;
use App\Support\Adressage;
use Illuminate\Console\Command;

class ImporterAdressesCommand extends Command
{
    protected $signature = 'ban:importer-adresses {fichier}';

    protected $description = "Importe un export BAN (CSV.gz) filtre aux communes deja presentes dans le cadastre importe (perimetre de l'EPCI)";

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $fichier = $this->argument('fichier');

        if (! is_file($fichier)) {
            $this->error("Fichier introuvable : {$fichier}");

            return self::FAILURE;
        }

        $communesEpci = ParcelleVersion::query()->distinct()->pluck('commune_insee')->flip();

        if ($communesEpci->isEmpty()) {
            $this->error("Aucune parcelle importee : importez d'abord le cadastre pour connaitre le perimetre de l'EPCI.");

            return self::FAILURE;
        }

        $this->info($communesEpci->count().' communes dans le perimetre.');

        $batch = ImportBatch::create([
            'source' => 'ban_adresses',
            'fichier_origine' => $fichier,
            'statut' => 'en_cours',
            'demarre_le' => now(),
        ]);

        $handle = gzopen($fichier, 'r');
        $entetes = str_getcsv(rtrim(gzgets($handle)), ';');

        $total = 0;
        $retenues = 0;
        $lignes = [];
        $maintenant = now();
        $bar = $this->output->createProgressBar();
        $bar->start();

        while (($ligne = gzgets($handle)) !== false) {
            $total++;
            $bar->advance();

            $valeurs = str_getcsv(rtrim($ligne, "\r\n"), ';');

            if (count($valeurs) !== count($entetes)) {
                continue;
            }

            $props = array_combine($entetes, $valeurs);

            if (! $communesEpci->has($props['code_insee'])) {
                continue;
            }

            $cadParcelles = $props['cad_parcelles'] !== ''
                ? explode('|', $props['cad_parcelles'])
                : null;

            $lignes[] = [
                'id_ban' => $props['id'],
                'numero' => $props['numero'] ?: null,
                'repetition' => $props['rep'] ?: null,
                'nom_voie' => $props['nom_voie'],
                'nom_voie_normalise' => Adressage::normaliser($props['nom_voie']),
                'code_postal' => $props['code_postal'] ?: null,
                'code_insee' => $props['code_insee'],
                'nom_commune' => $props['nom_commune'] ?: null,
                'lon' => $props['lon'] !== '' ? (float) $props['lon'] : null,
                'lat' => $props['lat'] !== '' ? (float) $props['lat'] : null,
                'cad_parcelles' => $cadParcelles ? json_encode($cadParcelles) : null,
                'proprietes_brutes' => json_encode($props, JSON_UNESCAPED_UNICODE),
                'import_batch_id' => $batch->id,
                'created_at' => $maintenant,
            ];
            $retenues++;

            if (count($lignes) >= 500) {
                AdresseVersion::insert($lignes);
                $lignes = [];
            }
        }

        if ($lignes !== []) {
            AdresseVersion::insert($lignes);
        }

        gzclose($handle);
        $bar->finish();
        $this->newLine();

        $batch->update([
            'statut' => 'termine',
            'nombre_lignes' => $retenues,
            'termine_le' => now(),
            'commentaire' => "{$total} lignes lues, {$retenues} retenues (communes de l'EPCI)",
        ]);

        $this->info("Import termine : {$retenues} adresses retenues sur {$total} lues (batch #{$batch->id}).");

        return self::SUCCESS;
    }
}
