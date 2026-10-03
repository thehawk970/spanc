<?php

namespace App\Console\Commands;

use App\Models\CompteurVersion;
use App\Models\ImportBatch;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Import brut uniquement. L'abonné SOGEDO n'est pas forcément le
 * propriétaire actuel (locataire, ancien propriétaire...) et son adresse
 * de facturation peut être sans rapport avec l'installation (déménagement).
 * Le rapprochement abonné/parcelle/propriétaire se fait séparément, plus
 * tard, une fois une logique de correspondance définie.
 */
class ImporterCompteursSogedoCommand extends Command
{
    protected $signature = 'sogedo:importer-compteurs {fichier}';

    protected $description = "Importe un export SOGEDO (compteurs d'eau/abonnés, XLSX) dans le référentiel versionné";

    public function handle(): int
    {
        $fichier = $this->argument('fichier');

        if (! is_file($fichier)) {
            $this->error("Fichier introuvable : {$fichier}");

            return self::FAILURE;
        }

        $batch = ImportBatch::create([
            'source' => 'sogedo_compteurs',
            'fichier_origine' => $fichier,
            'statut' => 'en_cours',
            'demarre_le' => now(),
        ]);

        $feuille = IOFactory::load($fichier)->getActiveSheet();

        $entetes = [];
        $total = 0;
        $ignorees = 0;
        $lignes = [];
        $maintenant = now();
        $numeroLigne = 0;

        foreach ($feuille->getRowIterator() as $ligne) {
            $numeroLigne++;

            $valeurs = [];
            foreach ($ligne->getCellIterator() as $cellule) {
                // La SOGEDO prefixe certaines colonnes texte d'une apostrophe
                // (force le format texte dans Excel) : ce n'est pas la donnee.
                $valeurs[] = ltrim(trim((string) $cellule->getFormattedValue()), "'");
            }

            if ($numeroLigne === 1) {
                $entetes = array_map('trim', $valeurs);

                continue;
            }

            if (count(array_filter($valeurs, fn ($v) => $v !== '')) === 0) {
                continue;
            }

            $props = array_combine($entetes, array_pad($valeurs, count($entetes), ''));
            $numeroCompteur = trim($props['Numero Compteur'] ?? '');

            if ($numeroCompteur === '') {
                $ignorees++;

                continue;
            }

            $adresse = trim(($props['Adresse Abonne'] ?? '').' '.($props['Adresse compl Abonne'] ?? ''));
            $commune = trim(($props['Code postal Abonne'] ?? '').' '.($props['Commune Abonne'] ?? ''));
            [$civilite, $nom] = $this->extraireCivilite(trim($props['Nom Abonne'] ?? ''));

            $lignes[] = [
                'numero_compteur' => $numeroCompteur,
                'adresse_brute' => trim("{$adresse}, {$commune}", ', '),
                'abonne_nom_brut' => $nom ?: null,
                'civilite' => $civilite,
                'proprietes_brutes' => json_encode($props, JSON_UNESCAPED_UNICODE),
                'import_batch_id' => $batch->id,
                'created_at' => $maintenant,
            ];
            $total++;

            if (count($lignes) >= 500) {
                CompteurVersion::insert($lignes);
                $lignes = [];
            }
        }

        if ($lignes !== []) {
            CompteurVersion::insert($lignes);
        }

        $batch->update([
            'statut' => 'termine',
            'nombre_lignes' => $total,
            'termine_le' => now(),
            'commentaire' => $ignorees > 0 ? "{$ignorees} ligne(s) ignoree(s) (sans numero de compteur)" : null,
        ]);

        $this->info("Import termine : {$total} compteurs importes, {$ignorees} ligne(s) ignoree(s) (batch #{$batch->id}).");

        return self::SUCCESS;
    }

    /**
     * La SOGEDO colle la civilite au nom, avec plusieurs graphies (parfois
     * sans espace interne : "M.ETMME", "M.OUMME"). Separee ici plutot que
     * jetee : "M. ou Mme" signale une indivision potentielle, utile pour le
     * rapprochement proprietaire a venir.
     *
     * @return array{0: ?string, 1: string}
     */
    private function extraireCivilite(string $nomBrut): array
    {
        $prefixes = [
            'M.ETMME' => 'M. et Mme',
            'M.OUMME' => 'M. ou Mme',
            'MMES' => 'Mmes',
            'MME' => 'Mme',
            'M.' => 'M.',
        ];

        foreach ($prefixes as $motif => $civilite) {
            $longueur = strlen($motif);

            if (str_starts_with($nomBrut, $motif)
                && (strlen($nomBrut) === $longueur || $nomBrut[$longueur] === ' ')) {
                return [$civilite, trim(substr($nomBrut, $longueur))];
            }
        }

        return [null, $nomBrut];
    }
}
