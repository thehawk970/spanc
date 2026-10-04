<?php

namespace App\Support;

/**
 * Plusieurs sources (SOGEDO, export cadastre logements) collent la civilité
 * au nom dans un seul champ texte libre, avec des graphies variables
 * (parfois sans espace interne : "M.ETMME", "M.OUMME"). Séparée plutôt que
 * jetée : "M. ou Mme" signale une indivision potentielle, utile pour le
 * rapprochement propriétaire.
 */
class Civilite
{
    private const PREFIXES = [
        'M.ETMME' => 'M. et Mme',
        'M ET MME' => 'M. et Mme',
        'M.OUMME' => 'M. ou Mme',
        'M OU MME' => 'M. ou Mme',
        'MMES' => 'Mmes',
        'MME' => 'Mme',
        'MLLE' => 'Mlle',
        'M.' => 'M.',
        'M' => 'M.',
    ];

    /** @return array{0: ?string, 1: string} [civilite, reste du texte] */
    public static function extraire(string $texteBrut): array
    {
        foreach (self::PREFIXES as $motif => $civilite) {
            $longueur = strlen($motif);

            if (str_starts_with($texteBrut, $motif)
                && (strlen($texteBrut) === $longueur || $texteBrut[$longueur] === ' ')) {
                return [$civilite, trim(substr($texteBrut, $longueur))];
            }
        }

        return [null, $texteBrut];
    }
}
