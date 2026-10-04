<?php

namespace App\Console\Commands;

use App\Models\AdresseVersion;
use App\Models\BatimentVersion;
use App\Models\Installation;
use App\Models\InstallationBatimentCourant;
use App\Models\InstallationBatimentEvenement;
use App\Models\InstallationEtat;
use App\Models\InstallationParcelleCourante;
use App\Models\InstallationParcelleEvenement;
use App\Models\ParcelleVersion;
use App\Support\IndexSpatialParcelles;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Une installation par adresse BAN (pas par bâtiment) : l'adresse postale
 * est mieux corrélée aux propriétaires et aux lieux de vie réels que le
 * centroïde d'un bâtiment dans un polygone cadastral, qui rate les
 * bâtiments en bordure de parcelle (cas trouvé en creusant pourquoi un
 * propriétaire réconcilié restait sans installation : aucun bâtiment
 * rapproché à quelques mètres près).
 *
 * Le bâtiment le plus proche, quand il existe à proximité raisonnable, est
 * rattaché pour enrichir l'installation (type maison/annexe) — sans être
 * obligatoire : une installation sans bâtiment proche reste créée et
 * visible, à vérifier sur le terrain.
 *
 * Les bâtiments sans adresse à proximité restent volontairement non
 * référencés plutôt que rattachés de force : ils seront convertis
 * manuellement en installation indépendante lors d'une visite terrain
 * (bouton sur BatimentVersionResource), notamment quand plusieurs
 * bâtiments distincts partagent un seul compteur d'eau.
 */
class BootstrapInstallationsDepuisAdressesCommand extends Command
{
    /** ~100m : même grille que IndexSpatialParcelles. */
    private const TAILLE_CELLULE = 0.001;

    /** ~50m : rayon de recherche du bâtiment le plus proche d'une adresse. */
    private const RAYON_BATIMENT_DEGRES = 0.00045;

    protected $signature = 'installations:bootstrap-depuis-adresses {--dry-run : Affiche les compteurs sans ecrire}';

    protected $description = 'Cree une installation par adresse BAN non encore couverte, avec rattachement du batiment le plus proche quand il existe';

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');

        $dryRun = (bool) $this->option('dry-run');

        $parcellesCouvertes = InstallationParcelleCourante::pluck('parcelle_id')->unique()->mapWithKeys(fn ($id) => [$id => true]);

        $this->info('Construction de l\'index spatial des parcelles...');
        $parcellesVersions = ParcelleVersion::all(['id', 'parcelle_id', 'geometry']);
        $parcelleIdParVersion = $parcellesVersions->pluck('parcelle_id', 'id');
        $indexParcelles = new IndexSpatialParcelles($parcellesVersions);

        $this->info('Construction de l\'index spatial des batiments non references...');
        $batimentsReferences = InstallationBatimentCourant::pluck('batiment_version_id')->mapWithKeys(fn ($id) => [$id => true]);
        $batiments = BatimentVersion::all(['id', 'type', 'centroide_lon', 'centroide_lat'])
            ->reject(fn (BatimentVersion $b) => $batimentsReferences->has($b->id));
        $grilleBatiments = $this->indexerBatimentsParCellule($batiments);

        $adresses = AdresseVersion::all(['id', 'cad_parcelles', 'lon', 'lat']);
        $this->info("{$adresses->count()} adresses a examiner.");

        $creees = 0;
        $dejaCouvertes = 0;
        $avecParcelle = 0;
        $sansParcelle = 0;
        $batimentsLies = 0;
        $annexesLiees = 0;

        $bar = $this->output->createProgressBar($adresses->count());
        $bar->start();

        DB::transaction(function () use (
            $adresses, $indexParcelles, $parcelleIdParVersion, $grilleBatiments, $batimentsReferences,
            &$parcellesCouvertes, $dryRun, $bar,
            &$creees, &$dejaCouvertes, &$avecParcelle, &$sansParcelle, &$batimentsLies, &$annexesLiees
        ) {
            foreach ($adresses as $adresse) {
                $bar->advance();

                $cadParcelles = $adresse->cad_parcelles ?: [];
                $confiance = 1.0;

                $parcelleIds = $cadParcelles;

                if ($parcelleIds === []) {
                    $replis = $this->replisGeometrique($adresse, $indexParcelles, $parcelleIdParVersion);
                    $parcelleIds = $replis === null ? [] : [$replis];
                    $confiance = 0.7;
                }

                if ($parcelleIds !== [] && collect($parcelleIds)->contains(fn ($pid) => $parcellesCouvertes->has($pid))) {
                    $dejaCouvertes++;

                    continue;
                }

                $parcelleIds === [] ? $sansParcelle++ : $avecParcelle++;

                if ($dryRun) {
                    $creees++;

                    continue;
                }

                $installation = Installation::create([]);

                InstallationEtat::create([
                    'installation_id' => $installation->id,
                    'type' => 'non_determine',
                    'statut' => 'a_statuer',
                    'motif' => 'Bootstrap automatique depuis adresse BAN',
                ]);

                foreach ($parcelleIds as $parcelleId) {
                    InstallationParcelleEvenement::create([
                        'installation_id' => $installation->id,
                        'parcelle_id' => $parcelleId,
                        'action' => 'lier',
                        'source' => 'auto',
                        'confiance' => $confiance,
                    ]);
                    $parcellesCouvertes[$parcelleId] = true;
                }

                $batimentPrincipal = $this->batimentLePlusProche($grilleBatiments, $batimentsReferences, $adresse->lon, $adresse->lat);

                if ($batimentPrincipal === null) {
                    $creees++;

                    continue;
                }

                InstallationBatimentEvenement::create([
                    'installation_id' => $installation->id,
                    'batiment_version_id' => $batimentPrincipal->id,
                    'action' => 'lier',
                    'source' => 'auto',
                    'confiance' => 1.0,
                    'motif' => "Batiment le plus proche de l'adresse BAN",
                ]);
                $batimentsReferences[$batimentPrincipal->id] = true;
                $batimentsLies++;

                foreach ($this->batimentsProchesDe($grilleBatiments, $batimentsReferences, $batimentPrincipal) as $annexe) {
                    InstallationBatimentEvenement::create([
                        'installation_id' => $installation->id,
                        'batiment_version_id' => $annexe->id,
                        'action' => 'lier',
                        'source' => 'auto',
                        'motif' => 'Batiment proche du batiment principal de cette installation',
                    ]);
                    $batimentsReferences[$annexe->id] = true;
                    $annexesLiees++;
                }

                $creees++;
            }
        });

        $bar->finish();
        $this->newLine();

        $prefixe = $dryRun ? '[dry-run] ' : '';
        $this->info(
            "{$prefixe}Termine : {$creees} installations ({$avecParcelle} avec parcelle, {$sansParcelle} sans), "
            ."{$dejaCouvertes} adresses deja couvertes (ignorees), {$batimentsLies} batiments principaux lies, {$annexesLiees} batiments supplementaires balayes."
        );

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, string>  $parcelleIdParVersion
     */
    private function replisGeometrique(AdresseVersion $adresse, IndexSpatialParcelles $index, Collection $parcelleIdParVersion): ?string
    {
        if ($adresse->lon === null || $adresse->lat === null) {
            return null;
        }

        $versionId = $index->trouverParcelleContenant($adresse->lon, $adresse->lat);

        return $versionId === null ? null : $parcelleIdParVersion[$versionId];
    }

    /**
     * @param  Collection<int, BatimentVersion>  $batiments
     * @return array<string, array<int, BatimentVersion>>
     */
    private function indexerBatimentsParCellule(Collection $batiments): array
    {
        $grille = [];

        foreach ($batiments as $batiment) {
            $grille[$this->cellule($batiment->centroide_lon, $batiment->centroide_lat)][] = $batiment;
        }

        return $grille;
    }

    private function cellule(float $lon, float $lat): string
    {
        $i = (int) floor($lon / self::TAILLE_CELLULE);
        $j = (int) floor($lat / self::TAILLE_CELLULE);

        return "{$i}_{$j}";
    }

    /**
     * @param  array<string, array<int, BatimentVersion>>  $grille
     * @return array<int, BatimentVersion> indexe par batiment_version_id
     */
    private function candidatsAutourDe(array $grille, float $lon, float $lat): array
    {
        $i = (int) floor($lon / self::TAILLE_CELLULE);
        $j = (int) floor($lat / self::TAILLE_CELLULE);

        $candidats = [];
        for ($di = -1; $di <= 1; $di++) {
            for ($dj = -1; $dj <= 1; $dj++) {
                $cellule = ($i + $di).'_'.($j + $dj);
                foreach ($grille[$cellule] ?? [] as $batiment) {
                    $candidats[$batiment->id] = $batiment;
                }
            }
        }

        return $candidats;
    }

    /**
     * @param  array<string, array<int, BatimentVersion>>  $grille
     * @param  Collection<array-key, bool>  $batimentsReferences  set des id deja references
     */
    private function batimentLePlusProche(array $grille, Collection $batimentsReferences, ?float $lon, ?float $lat): ?BatimentVersion
    {
        if ($lon === null || $lat === null) {
            return null;
        }

        $candidats = array_filter(
            $this->candidatsAutourDe($grille, $lon, $lat),
            fn (BatimentVersion $b) => ! $batimentsReferences->has($b->id)
        );

        if ($candidats === []) {
            return null;
        }

        $maisons = array_filter($candidats, fn (BatimentVersion $b) => $b->type === '01');
        $pool = $maisons !== [] ? $maisons : $candidats;

        $meilleur = null;
        $meilleureDistance = self::RAYON_BATIMENT_DEGRES;

        foreach ($pool as $batiment) {
            $distance = $this->distance($lon, $lat, $batiment->centroide_lon, $batiment->centroide_lat);

            if ($distance <= $meilleureDistance) {
                $meilleur = $batiment;
                $meilleureDistance = $distance;
            }
        }

        return $meilleur;
    }

    /**
     * Uniquement les annexes (type 02) : une maison voisine (type 01) est une
     * habitation distincte avec sa propre adresse, elle ne doit jamais être
     * absorbée par le balayage d'une autre installation — sinon un centre de
     * village dense (plusieurs maisons en moins de 50m) se retrouve fusionné
     * en une seule installation au lieu d'une par maison.
     *
     * @param  array<string, array<int, BatimentVersion>>  $grille
     * @param  Collection<array-key, bool>  $batimentsReferences  set des id deja references
     * @return array<int, BatimentVersion>
     */
    private function batimentsProchesDe(array $grille, Collection $batimentsReferences, BatimentVersion $principal): array
    {
        $candidats = $this->candidatsAutourDe($grille, $principal->centroide_lon, $principal->centroide_lat);
        unset($candidats[$principal->id]);

        return array_filter(
            $candidats,
            fn (BatimentVersion $b) => $b->type === '02'
                && ! $batimentsReferences->has($b->id)
                && $this->distance($principal->centroide_lon, $principal->centroide_lat, $b->centroide_lon, $b->centroide_lat) <= self::RAYON_BATIMENT_DEGRES
        );
    }

    /** Distance approximative (equirectangulaire), suffisante a l'echelle d'une parcelle. */
    private function distance(float $lon1, float $lat1, float $lon2, float $lat2): float
    {
        $dx = ($lon2 - $lon1) * cos(deg2rad($lat1));
        $dy = $lat2 - $lat1;

        return sqrt($dx ** 2 + $dy ** 2);
    }
}
