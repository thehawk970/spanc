<?php

namespace App\Support;

use Generator;
use InvalidArgumentException;

/**
 * Géométrie GeoJSON minimale (Polygon / MultiPolygon) pour le rapprochement
 * cadastral. Les trous (anneaux intérieurs) sont ignorés par choix : c'est
 * une heuristique de rapprochement semi-automatique validée par un humain
 * ensuite, pas un calcul géodésique de précision.
 */
class Geometrie
{
    /** Centroïde approximatif (moyenne des sommets du premier anneau extérieur). */
    public static function centroideApproximatif(array $geometry): array
    {
        $anneau = self::premierAnneauExterieur($geometry);
        $n = count($anneau);
        $sommeLon = 0.0;
        $sommeLat = 0.0;

        foreach ($anneau as [$lon, $lat]) {
            $sommeLon += $lon;
            $sommeLat += $lat;
        }

        return [$sommeLon / $n, $sommeLat / $n];
    }

    /** Bounding box [minLon, maxLon, minLat, maxLat] sur toute la géométrie. */
    public static function bbox(array $geometry): array
    {
        $minLon = $minLat = INF;
        $maxLon = $maxLat = -INF;

        foreach (self::tousLesPoints($geometry) as [$lon, $lat]) {
            $minLon = min($minLon, $lon);
            $maxLon = max($maxLon, $lon);
            $minLat = min($minLat, $lat);
            $maxLat = max($maxLat, $lat);
        }

        return [$minLon, $maxLon, $minLat, $maxLat];
    }

    /** Ray casting classique sur un anneau simple. */
    public static function pointDansAnneau(float $lon, float $lat, array $anneau): bool
    {
        $dedans = false;
        $n = count($anneau);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $anneau[$i];
            [$xj, $yj] = $anneau[$j];

            $intersecte = (($yi > $lat) !== ($yj > $lat))
                && ($lon < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi);

            if ($intersecte) {
                $dedans = ! $dedans;
            }
        }

        return $dedans;
    }

    /** Teste le point contre l'anneau extérieur de chaque partie (Polygon ou MultiPolygon). */
    public static function pointDansGeometrie(float $lon, float $lat, array $geometry): bool
    {
        foreach (self::anneauxExterieurs($geometry) as $anneau) {
            if (self::pointDansAnneau($lon, $lat, $anneau)) {
                return true;
            }
        }

        return false;
    }

    private static function premierAnneauExterieur(array $geometry): array
    {
        return match ($geometry['type']) {
            'Polygon' => $geometry['coordinates'][0],
            'MultiPolygon' => $geometry['coordinates'][0][0],
            default => throw new InvalidArgumentException("Type de geometrie non supporte: {$geometry['type']}"),
        };
    }

    private static function anneauxExterieurs(array $geometry): array
    {
        return match ($geometry['type']) {
            'Polygon' => [$geometry['coordinates'][0]],
            'MultiPolygon' => array_map(fn ($polygone) => $polygone[0], $geometry['coordinates']),
            default => throw new InvalidArgumentException("Type de geometrie non supporte: {$geometry['type']}"),
        };
    }

    private static function tousLesPoints(array $geometry): Generator
    {
        $parties = $geometry['type'] === 'Polygon' ? [$geometry['coordinates']] : $geometry['coordinates'];

        foreach ($parties as $anneaux) {
            foreach ($anneaux as $anneau) {
                foreach ($anneau as $point) {
                    yield $point;
                }
            }
        }
    }
}
