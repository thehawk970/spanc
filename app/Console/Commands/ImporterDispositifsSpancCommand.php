<?php

namespace App\Console\Commands;

use App\Models\DispositifSpancVersion;
use App\Models\ImportBatch;
use Illuminate\Console\Command;

/**
 * Import brut uniquement. Export du logiciel SPANC historique (Perigeo) : un
 * dispositif deja suivi par le service, avec le resume de son dernier
 * controle. Pas de rapprochement ici (voir cadastre:importer-controles pour
 * l'historique detaille, relie a ces dossiers via reference_dossier).
 *
 * D'autres logiciels de diagnostic sont prevus plus tard : la provenance
 * est tracee au niveau du batch (source "dispositifs_spanc_perigeo"), pas
 * dans le schema, pour que les futures sources s'ajoutent sans migration.
 */
class ImporterDispositifsSpancCommand extends Command
{
    protected $signature = 'cadastre:importer-dispositifs-spanc {fichier}';

    protected $description = "Importe un export Perigeo (dispositifs SPANC suivis, CSV ';') dans le referentiel versionne";

    public function handle(): int
    {
        $fichier = $this->argument('fichier');

        if (! is_file($fichier)) {
            $this->error("Fichier introuvable : {$fichier}");

            return self::FAILURE;
        }

        $batch = ImportBatch::create([
            'source' => 'dispositifs_spanc_perigeo',
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
            $referenceDossier = trim($props['Référence dossier'] ?? '');

            if ($referenceDossier === '') {
                $ignorees++;

                continue;
            }

            ['section' => $section, 'numero' => $numero] = $this->sectionNumero($props['Référence cadastrale'] ?? '');

            $lignes[] = [
                'reference_dossier' => $referenceDossier,
                'code_insee' => trim($props['code insee'] ?? '') ?: null,
                'section' => $section,
                'numero' => $numero,
                'date_derniere_visite' => $this->dateFrancaise($props['Date de dernière visite'] ?? ''),
                'technicien' => trim($props['Technicien SPANC'] ?? '') ?: null,
                'nature_dernier_controle' => trim($props['Nature du dernier contrôle'] ?? '') ?: null,
                'avis_dernier_controle' => trim($props['Avis du dernier contrôle'] ?? '') ?: null,
                'type_filiere' => trim($props['Type de filière'] ?? '') ?: null,
                'nom_cadastre' => trim($props['Nom (source cadastre)'] ?? '') ?: null,
                'prenom_cadastre' => trim($props['Prénom (source cadastre)'] ?? '') ?: null,
                'nom_spanc' => trim($props['Nom (source SPANC)'] ?? '') ?: null,
                'prenom_spanc' => trim($props['Prénom (source SPANC)'] ?? '') ?: null,
                'proprietes_brutes' => json_encode($props, JSON_UNESCAPED_UNICODE),
                'import_batch_id' => $batch->id,
                'created_at' => $maintenant,
            ];
            $total++;

            if (count($lignes) >= 500) {
                DispositifSpancVersion::insert($lignes);
                $lignes = [];
            }
        }

        fclose($poignee);

        if ($lignes !== []) {
            DispositifSpancVersion::insert($lignes);
        }

        $batch->update([
            'statut' => 'termine',
            'nombre_lignes' => $total,
            'termine_le' => now(),
            'commentaire' => $ignorees > 0 ? "{$ignorees} ligne(s) ignoree(s) (sans reference dossier)" : null,
        ]);

        $this->info("Import termine : {$total} dispositifs importes, {$ignorees} ligne(s) ignoree(s) (batch #{$batch->id}).");

        return self::SUCCESS;
    }

    /**
     * "AB0134" -> section "AB", numero "134" (sans zeros de tete, comme
     * stocke dans parcelle_versions.numero). Null si non reconnu.
     *
     * @return array{section: ?string, numero: ?string}
     */
    private function sectionNumero(string $referenceCadastrale): array
    {
        if (preg_match('/^([A-Z]+)0*(\d+)$/', trim($referenceCadastrale), $m)) {
            return ['section' => $m[1], 'numero' => $m[2]];
        }

        return ['section' => null, 'numero' => null];
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
