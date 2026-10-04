<?php

namespace App\Support;

/**
 * Une parcelle peut porter plusieurs maisons distinctes (hameau, corps de
 * ferme...) : quand une annexe doit être rattachée à « sa » maison, on
 * suppose qu'elle dessert la plus proche d'entre elles (distance du
 * centroïde, approximation suffisante à l'échelle d'une parcelle — même
 * heuristique semi-automatique que le rapprochement bâtiment/parcelle).
 */
class RapprochementAnnexe
{
    /**
     * @param  array<string, array{0: float, 1: float}>  $candidats  installation_id => [centroide_lon, centroide_lat]
     */
    public static function plusProche(array $candidats, float $lon, float $lat): ?string
    {
        $meilleur = null;
        $meilleureDistance = INF;

        foreach ($candidats as $installationId => [$candidatLon, $candidatLat]) {
            $dx = ($candidatLon - $lon) * cos(deg2rad($lat));
            $dy = $candidatLat - $lat;
            $distance = $dx ** 2 + $dy ** 2;

            if ($distance < $meilleureDistance) {
                $meilleureDistance = $distance;
                $meilleur = $installationId;
            }
        }

        return $meilleur;
    }
}
