<?php

namespace App\Console\Commands;

use App\Models\AdresseVersion;
use App\Models\FicheSpancPyaVersion;
use App\Models\ImportBatch;
use App\Models\ParcelleVersion;
use App\Support\Adressage;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as DateExcel;

/**
 * Import brut + rapprochement parcelle. Export d'un deuxieme logiciel de
 * diagnostic SPANC (fiches detaillees, XLSX), distinct de Perigeo. Lie
 * uniquement a la parcelle, jamais au proprietaire (source potentiellement
 * ancienne, le proprietaire a pu changer depuis).
 *
 * Deux champs source sans equivalent direct en base, resolus ici :
 * - "Ville (terrain)" (texte libre tres variable) -> code_insee, voir
 *   resoudreCommune().
 * - "Section et numero(s) de parcelle(s)" (parfois plusieurs parcelles par
 *   fiche, separateurs varies : "-", "/", espace) -> parcelle_ids (tableau),
 *   voir extraireParcelles(). Chaque parcelle est cherchee par
 *   (code_insee, section, numero) sur parcelle_versions — pas de
 *   reconstruction directe de parcelle_id, le prefixe cadastral n'est pas
 *   toujours "0000" (confirme empiriquement sur dispositif_spanc_versions,
 *   ex. Pays de Belves).
 *
 * Lecture cellule par cellule en valeur brute (pas getFormattedValue(), ni
 * toArray()/rangeToArray()) : le moteur de calcul des formats de nombre de
 * PhpSpreadsheet plante sur ce fichier precis des qu'on lui demande une
 * valeur formatee (confirme en isolant la cellule fautive) — voir
 * valeurCellule().
 */
class ImporterFichesSpancPyaCommand extends Command
{
    protected $signature = 'cadastre:importer-fiches-spanc-pya {fichier}';

    protected $description = 'Importe un export XLSX du deuxieme logiciel de diagnostic SPANC (fiches detaillees) dans le referentiel versionne';

    private const NB_COLONNES = 60;

    /** Alias -> nom de commune actuel normalise (anciennes communes fusionnees, formes partielles). */
    private const ALIAS_COMMUNES = [
        'MOUZENS' => 'COUX ET BIGAROQUE MOUZENS',
        'BEZENAC' => 'CASTELS ET BEZENAC',
        'CASTELS' => 'CASTELS ET BEZENAC',
        'CASTELS BEZENAC' => 'CASTELS ET BEZENAC',
        'SIORAC' => 'SIORAC EN PERIGORD',
        'BELVES' => 'PAYS DE BELVES',
        'COUX ET BIGAROQUE' => 'COUX ET BIGAROQUE MOUZENS',
    ];

    public function handle(): int
    {
        ini_set('memory_limit', '2048M');

        $fichier = $this->argument('fichier');

        if (! is_file($fichier)) {
            $this->error("Fichier introuvable : {$fichier}");

            return self::FAILURE;
        }

        $communeParNomNormalise = $this->communeParNomNormalise();

        $batch = ImportBatch::create([
            'source' => 'fiches_spanc_pya',
            'fichier_origine' => $fichier,
            'statut' => 'en_cours',
            'demarre_le' => now(),
        ]);

        $ws = IOFactory::load($fichier)->getActiveSheet();
        $plusHauteLigne = $ws->getHighestRow();

        $entetes = [];
        for ($col = 1; $col <= self::NB_COLONNES; $col++) {
            $lettre = Coordinate::stringFromColumnIndex($col);
            $entetes[$col] = trim((string) $ws->getCell($lettre.'1')->getValue());
        }

        $this->info(($plusHauteLigne - 1).' lignes a examiner.');

        $total = 0;
        $ignorees = 0;
        $avecParcelle = 0;
        $sansCommune = 0;
        $sansParcelle = 0;
        $lignes = [];

        $bar = $this->output->createProgressBar($plusHauteLigne - 1);
        $bar->start();

        for ($r = 2; $r <= $plusHauteLigne; $r++) {
            $bar->advance();

            $props = [];
            $vide = true;
            for ($col = 1; $col <= self::NB_COLONNES; $col++) {
                $lettre = Coordinate::stringFromColumnIndex($col);
                $valeur = trim($this->valeurCellule($ws->getCell($lettre.$r)));
                $props[$entetes[$col]] = $valeur;
                if ($valeur !== '') {
                    $vide = false;
                }
            }

            if ($vide) {
                continue;
            }

            $villeBrute = $props['Ville (terrain)'] ?? '';
            $sectionNumeroBrute = $props['Section et numéro(s) de parcelle(s)'] ?? '';

            $codeInsee = $this->resoudreCommune($villeBrute, $communeParNomNormalise);
            $parcelleIds = $codeInsee ? $this->extraireParcelles($sectionNumeroBrute, $codeInsee) : [];

            if (! $codeInsee) {
                $sansCommune++;
            } elseif ($parcelleIds === []) {
                $sansParcelle++;
            } else {
                $avecParcelle++;
            }

            $lignes[] = [
                'id_source' => trim($props['Id'] ?? '') ?: null,
                'date_controle' => $this->dateAmericaine($props['Date du contrôle'] ?? ''),
                'nature_dernier_controle' => trim($props['Nature du dernier contrôle'] ?? '') ?: null,
                'avis' => trim($props['Avis sur l\'installation'] ?? '') ?: null,
                'conformite' => trim($props['Conformité'] ?? '') ?: null,
                'ville_terrain_brute' => $villeBrute ?: null,
                'code_insee_resolu' => $codeInsee,
                'section_numero_brute' => $sectionNumeroBrute ?: null,
                'parcelle_ids' => json_encode($parcelleIds),
                'proprietes_brutes' => json_encode($props, JSON_UNESCAPED_UNICODE),
                'import_batch_id' => $batch->id,
                'created_at' => now(),
            ];
            $total++;

            if (count($lignes) >= 300) {
                FicheSpancPyaVersion::insert($lignes);
                $lignes = [];
            }
        }

        if ($lignes !== []) {
            FicheSpancPyaVersion::insert($lignes);
        }

        $bar->finish();
        $this->newLine();

        $batch->update([
            'statut' => 'termine',
            'nombre_lignes' => $total,
            'termine_le' => now(),
            'commentaire' => "{$ignorees} ligne(s) vide(s) ignoree(s)",
        ]);

        $this->info("Import termine : {$total} fiches importees, {$avecParcelle} avec au moins une parcelle resolue, {$sansCommune} sans commune reconnue, {$sansParcelle} commune reconnue mais aucune parcelle trouvee (batch #{$batch->id}).");

        return self::SUCCESS;
    }

    /** @return array<string, string> nom de commune normalise -> code_insee */
    private function communeParNomNormalise(): array
    {
        $officielles = AdresseVersion::query()
            ->whereNotNull('nom_commune')
            ->distinct()
            ->get(['code_insee', 'nom_commune'])
            ->mapWithKeys(fn ($c) => [Adressage::normaliser($c->nom_commune) => $c->code_insee])
            ->all();

        foreach (self::ALIAS_COMMUNES as $alias => $nomNormalise) {
            if (isset($officielles[$nomNormalise])) {
                $officielles[$alias] = $officielles[$nomNormalise];
            }
        }

        return $officielles;
    }

    /**
     * Normalise, developpe "ST"/"STE" en "SAINT"/"SAINTE" et retire un
     * article de tete ("LE "/"LA ") avant de chercher une correspondance
     * exacte (communes officielles) ou un alias (anciennes communes).
     *
     * @param  array<string, string>  $communeParNomNormalise
     */
    private function resoudreCommune(string $villeBrute, array $communeParNomNormalise): ?string
    {
        if (trim($villeBrute) === '') {
            return null;
        }

        $normalise = Adressage::normaliser($villeBrute);
        $normalise = preg_replace('/^(LE|LA) /', '', $normalise) ?? $normalise;
        $normalise = preg_replace('/\bSTE\b/', 'SAINTE', $normalise) ?? $normalise;
        $normalise = preg_replace('/\bST\b/', 'SAINT', $normalise) ?? $normalise;

        if (isset($communeParNomNormalise[$normalise])) {
            return $communeParNomNormalise[$normalise];
        }

        // "ST" est ambigu (Saint/Sainte) : certaines fiches abrègent un nom
        // féminin ("ST FOY" pour "Sainte-Foy") avec la forme masculine.
        $normaliseFeminin = preg_replace('/^SAINT /', 'SAINTE ', $normalise) ?? $normalise;

        return $communeParNomNormalise[$normaliseFeminin] ?? null;
    }

    /**
     * "AI n°46-47-49-402-403" -> [(AI,46),(AI,47),(AI,49),(AI,402),(AI,403)].
     * "AC54 AC55" -> [(AC,54),(AC,55)] (plusieurs groupes section+numero
     * sans separateur). Chaque paire est cherchee sur parcelle_versions ;
     * seules celles trouvees sont retenues.
     *
     * @return array<int, string>
     */
    private function extraireParcelles(string $sectionNumeroBrute, string $codeInsee): array
    {
        if (trim($sectionNumeroBrute) === '') {
            return [];
        }

        $texte = mb_strtoupper($sectionNumeroBrute);

        if (! preg_match_all('/([A-Z]{1,3})\s*N?°?\s*((?:\d+[\/\-]?)+)/u', $texte, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $parcelleIds = [];

        foreach ($matches as $match) {
            $section = $match[1];
            $numeros = array_filter(preg_split('/[\/\-]/', rtrim($match[2], '/-')) ?: []);

            foreach ($numeros as $numero) {
                $numero = ltrim($numero, '0') ?: '0';

                $parcelle = ParcelleVersion::where('commune_insee', $codeInsee)
                    ->where('section', $section)
                    ->where('numero', $numero)
                    ->first();

                if ($parcelle) {
                    $parcelleIds[] = $parcelle->parcelle_id;
                }
            }
        }

        return array_values(array_unique($parcelleIds));
    }

    /**
     * Valeur brute, pas getFormattedValue() : plante sur ce fichier precis
     * (bug PhpSpreadsheet dans le moteur de calcul des formats de nombre,
     * confirme en isolant la ligne fautive — la cellule seule n'est pas en
     * cause). Les cellules de type date sont reconverties manuellement en
     * chaine "M/J/AAAA" pour rester compatibles avec dateAmericaine().
     */
    private function valeurCellule(Cell $cell): string
    {
        $valeur = $cell->getValue();

        if ($valeur === null) {
            return '';
        }

        if (is_numeric($valeur) && DateExcel::isDateTime($cell)) {
            return DateExcel::excelToDateTimeObject((float) $valeur)->format('n/j/Y');
        }

        if (is_float($valeur) && floor($valeur) === $valeur) {
            return (string) (int) $valeur;
        }

        return (string) $valeur;
    }

    private function dateAmericaine(string $valeur): ?string
    {
        $valeur = trim($valeur);

        if ($valeur === '' || ! preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $valeur, $m)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]);
    }
}
