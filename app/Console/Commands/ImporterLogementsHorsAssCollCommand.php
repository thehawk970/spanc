<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Models\LogementHorsAssCollVersion;
use Illuminate\Console\Command;

/**
 * Import brut uniquement. Export fiscal/cadastral (type MAJIC) des logements
 * hors assainissement collectif : une ligne par parcelle taxee, avec son
 * proprietaire (numero de compte + nom brut, personne physique ou morale) et
 * son idu cadastral. Les colonnes de suivi de controle SPANC du fichier
 * source sont volontairement ignorees ici : elles sont vides (fichier de
 * travail vierge), le suivi reel se fait dans ce logiciel, pas reimporte
 * depuis ce CSV.
 *
 * parcelle_id se deduit directement de l'idu (prefixe departement "24" +
 * idu) : pas de rapprochement geometrique/texte necessaire comme pour la
 * BAN ou SOGEDO, le lien parcelle est exact des l'import.
 */
class ImporterLogementsHorsAssCollCommand extends Command
{
    protected $signature = 'cadastre:importer-logements-hors-ass-coll {fichier}';

    protected $description = "Importe un export fiscal/cadastral des logements hors assainissement collectif (CSV ';', type MAJIC) dans le referentiel versionne";

    public function handle(): int
    {
        $fichier = $this->argument('fichier');

        if (! is_file($fichier)) {
            $this->error("Fichier introuvable : {$fichier}");

            return self::FAILURE;
        }

        $batch = ImportBatch::create([
            'source' => 'cadastre_logements_hors_ass_coll',
            'fichier_origine' => $fichier,
            'statut' => 'en_cours',
            'demarre_le' => now(),
        ]);

        $poignee = fopen($fichier, 'r');

        if ($poignee === false) {
            $this->error("Impossible d'ouvrir le fichier : {$fichier}");

            return self::FAILURE;
        }

        $entetes = null;
        $total = 0;
        $ignorees = 0;
        $lignes = [];
        $maintenant = now();

        while (($valeurs = fgetcsv($poignee, 0, ';')) !== false) {
            if ($entetes === null) {
                $valeurs[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $valeurs[0]) ?? $valeurs[0];
                $entetes = array_map(fn ($v) => trim((string) $v), $valeurs);

                continue;
            }

            if (count(array_filter($valeurs, fn ($v) => $v !== null && trim((string) $v) !== '')) === 0) {
                continue;
            }

            $props = array_combine($entetes, array_pad($valeurs, count($entetes), null));
            $idu = trim($props['idu'] ?? '');

            if ($idu === '') {
                $ignorees++;

                continue;
            }

            $nombreLocaux = trim($props['Nombre de locaux'] ?? '');

            $lignes[] = [
                'parcelle_id' => '24'.$idu,
                'numero_proprietaire' => trim($props['Numéro de propriétaire'] ?? '') ?: null,
                'proprietaire_nom_brut' => trim($props['Propriétaire'] ?? '') ?: null,
                'type_habitation' => trim($props['Type habitation'] ?? '') ?: null,
                'nombre_locaux' => $nombreLocaux !== '' && is_numeric($nombreLocaux) ? (int) $nombreLocaux : null,
                'proprietes_brutes' => json_encode($props, JSON_UNESCAPED_UNICODE),
                'import_batch_id' => $batch->id,
                'created_at' => $maintenant,
            ];
            $total++;

            if (count($lignes) >= 500) {
                LogementHorsAssCollVersion::insert($lignes);
                $lignes = [];
            }
        }

        fclose($poignee);

        if ($lignes !== []) {
            LogementHorsAssCollVersion::insert($lignes);
        }

        $batch->update([
            'statut' => 'termine',
            'nombre_lignes' => $total,
            'termine_le' => now(),
            'commentaire' => $ignorees > 0 ? "{$ignorees} ligne(s) ignoree(s) (sans idu)" : null,
        ]);

        $this->info("Import termine : {$total} logements importes, {$ignorees} ligne(s) ignoree(s) (batch #{$batch->id}).");

        return self::SUCCESS;
    }
}
