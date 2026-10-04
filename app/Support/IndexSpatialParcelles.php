<?php

namespace App\Support;

use Generator;

/**
 * Index spatial en grille pour retrouver la parcelle dont le polygone
 * contient un point donné, sans scanner toutes les parcelles (cellules
 * ~100m, assez fin pour que chaque cellule ne contienne qu'une poignée de
 * parcelles candidates). Même technique que cadastre:rapprocher-batiments,
 * extraite ici pour être réutilisée par le bootstrap par adresse.
 */
class IndexSpatialParcelles
{
    private const TAILLE_CELLULE = 0.001;

    /** @var array<string, array<int, int>> */
    private array $grille = [];

    /** @var array<int, array<mixed>> */
    private array $geometries = [];

    /**
     * @param  iterable<object{id: int, geometry: array<mixed>}>  $parcelles
     */
    public function __construct(iterable $parcelles)
    {
        foreach ($parcelles as $parcelle) {
            $this->geometries[$parcelle->id] = $parcelle->geometry;

            [$minLon, $maxLon, $minLat, $maxLat] = Geometrie::bbox($parcelle->geometry);

            foreach ($this->cellulesPourBbox($minLon, $maxLon, $minLat, $maxLat) as $cellule) {
                $this->grille[$cellule][] = $parcelle->id;
            }
        }
    }

    /** Id (clé passée au constructeur) de la parcelle contenant le point, ou null. */
    public function trouverParcelleContenant(float $lon, float $lat): ?int
    {
        foreach (array_unique($this->candidatsAutourDe($lon, $lat)) as $parcelleId) {
            if (Geometrie::pointDansGeometrie($lon, $lat, $this->geometries[$parcelleId])) {
                return $parcelleId;
            }
        }

        return null;
    }

    private function cellulesPourBbox(float $minLon, float $maxLon, float $minLat, float $maxLat): Generator
    {
        $iMin = (int) floor($minLon / self::TAILLE_CELLULE);
        $iMax = (int) floor($maxLon / self::TAILLE_CELLULE);
        $jMin = (int) floor($minLat / self::TAILLE_CELLULE);
        $jMax = (int) floor($maxLat / self::TAILLE_CELLULE);

        for ($i = $iMin; $i <= $iMax; $i++) {
            for ($j = $jMin; $j <= $jMax; $j++) {
                yield "{$i}_{$j}";
            }
        }
    }

    /** @return array<int, int> */
    private function candidatsAutourDe(float $lon, float $lat): array
    {
        $i = (int) floor($lon / self::TAILLE_CELLULE);
        $j = (int) floor($lat / self::TAILLE_CELLULE);

        $candidats = [];
        for ($di = -1; $di <= 1; $di++) {
            for ($dj = -1; $dj <= 1; $dj++) {
                $cellule = ($i + $di).'_'.($j + $dj);
                if (isset($this->grille[$cellule])) {
                    $candidats = array_merge($candidats, $this->grille[$cellule]);
                }
            }
        }

        return $candidats;
    }
}
