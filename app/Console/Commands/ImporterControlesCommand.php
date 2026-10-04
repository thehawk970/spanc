<?php

namespace App\Console\Commands;

use App\Models\ControleVersion;
use App\Models\ImportBatch;
use Illuminate\Console\Command;

/**
 * Import brut uniquement. Deux exports Perigeo aux colonnes quasi
 * identiques (contrôles "BE" = vérification de bonne exécution post-travaux,
 * "DPV" = diagnostic/vente/périodique) : --type choisit le mapping de
 * colonnes, les deux alimentent la même table controle_versions
 * (type_source distingue a posteriori) plutôt que deux tables redondantes.
 *
 * D'autres logiciels de diagnostic sont prévus plus tard : comme pour
 * dispositifs_spanc, la provenance est tracée au niveau du batch
 * ("controles_perigeo"), pas dans le schéma.
 */
class ImporterControlesCommand extends Command
{
    protected $signature = 'cadastre:importer-controles {fichier} {--type= : be ou dpv}';

    protected $description = "Importe un export Perigeo de controles (BE ou DPV, CSV ';') dans le referentiel versionne";

    /** @var array<string, array<string, string>> */
    private const COLONNES = [
        'be' => [
            'reference_controle' => 'Référence du contrôle',
            'commune' => 'Commune',
            'cadre_ou_type' => "Dans quel cadre s'effectue ce contrôle ?",
            'date_visite' => 'Date de la visite de contrôle',
            'avis' => 'Avis du service de contrôle sur ce dispositif',
        ],
        'dpv' => [
            'reference_controle' => 'Référence contrôle',
            'commune' => 'Commune (ATD)',
            'cadre_ou_type' => 'Type de contrôle',
            'date_visite' => 'Date de la visite',
            'avis' => 'Avis',
        ],
    ];

    public function handle(): int
    {
        $fichier = $this->argument('fichier');
        $type = $this->option('type');

        if (! in_array($type, ['be', 'dpv'], true)) {
            $this->error('--type doit valoir "be" ou "dpv".');

            return self::FAILURE;
        }

        if (! is_file($fichier)) {
            $this->error("Fichier introuvable : {$fichier}");

            return self::FAILURE;
        }

        $colonnes = self::COLONNES[$type];

        $batch = ImportBatch::create([
            'source' => 'controles_perigeo',
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
            $lienDossier = trim($props['Lien vers le dossier'] ?? '');

            if ($lienDossier === '' && trim($props[$colonnes['reference_controle']] ?? '') === '') {
                $ignorees++;

                continue;
            }

            $lignes[] = [
                'reference_controle' => trim($props[$colonnes['reference_controle']] ?? '') ?: null,
                'type_source' => $type,
                'reference_dossier_liee' => $this->referenceDossierLiee($lienDossier),
                'commune' => trim($props[$colonnes['commune']] ?? '') ?: null,
                'cadre_ou_type' => trim($props[$colonnes['cadre_ou_type']] ?? '') ?: null,
                'type_filiere' => trim($props['Type(s) de filière'] ?? '') ?: null,
                'lien_dossier' => $lienDossier ?: null,
                'date_visite' => $this->dateFrancaise($props[$colonnes['date_visite']] ?? ''),
                'technicien' => trim($props['Technicien'] ?? '') ?: null,
                'etat_controle' => trim($props['Etat du contrôle'] ?? '') ?: null,
                'avis' => trim($props[$colonnes['avis']] ?? '') ?: null,
                'proprietaire_brut' => trim($props['Propriétaire'] ?? '') ?: null,
                'usager_brut' => trim($props['Usager'] ?? '') ?: null,
                'adresse_parcelle_brute' => trim($props['Adresse parcelle'] ?? '') ?: null,
                'adresse_proprietaire_brute' => trim($props['Adresse propriétaire'] ?? '') ?: null,
                'proprietes_brutes' => json_encode($props, JSON_UNESCAPED_UNICODE),
                'import_batch_id' => $batch->id,
                'created_at' => $maintenant,
            ];
            $total++;

            if (count($lignes) >= 500) {
                ControleVersion::insert($lignes);
                $lignes = [];
            }
        }

        fclose($poignee);

        if ($lignes !== []) {
            ControleVersion::insert($lignes);
        }

        $batch->update([
            'statut' => 'termine',
            'nombre_lignes' => $total,
            'termine_le' => now(),
            'commentaire' => $ignorees > 0 ? "{$ignorees} ligne(s) ignoree(s) (sans reference)" : null,
        ]);

        $this->info("Import termine : {$total} controles ({$type}) importes, {$ignorees} ligne(s) ignoree(s) (batch #{$batch->id}).");

        return self::SUCCESS;
    }

    /**
     * "MME ROSELINE DUBUC / 24293_B0950_145422" -> "24293_B0950_145422",
     * meme format que dispositif_spanc_versions.reference_dossier.
     */
    private function referenceDossierLiee(string $lienDossier): ?string
    {
        if (preg_match('/(\d{5})_\s*([A-Z]+\d+)_(\d+)/', $lienDossier, $m)) {
            return "{$m[1]}_{$m[2]}_{$m[3]}";
        }

        return null;
    }

    private function dateFrancaise(string $valeur): ?string
    {
        $valeur = trim($valeur);

        if ($valeur === '' || ! preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $valeur, $m)) {
            return null;
        }

        return "{$m[3]}-{$m[2]}-{$m[1]}";
    }
}
