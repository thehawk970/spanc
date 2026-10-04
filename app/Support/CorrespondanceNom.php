<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Rapprochement nom/prénom entre deux sources qui ne découpent pas pareil
 * (un seul champ texte libre côté SOGEDO/cadastre, nom+prénom séparés côté
 * référentiel propriétaires). Même esprit que Adressage : simple, exact
 * après normalisation, le reste à valider humainement plutôt qu'un calcul
 * de similarité approximatif de plus.
 *
 * Le tiret des prénoms composés est volontairement conservé dans le
 * découpage (pas réduit à un espace comme Adressage::normaliser le ferait) :
 * "JEAN-MICHEL" et "JEAN-PAUL" ne doivent pas matcher via leur seul "JEAN"
 * commun, trop fréquent pour être un signal fiable.
 */
class CorrespondanceNom
{
    /**
     * Majuscules, sans accents, tiret conservé, reste de la ponctuation
     * réduite à des espaces.
     *
     * @return array<int, string>
     */
    public static function tokens(string $texte): array
    {
        $texte = Str::ascii($texte);
        $texte = mb_strtoupper($texte);
        $texte = preg_replace('/[^A-Z0-9\- ]/', ' ', $texte) ?? '';
        $texte = preg_replace('/\s+/', ' ', $texte) ?? '';

        return array_values(array_filter(explode(' ', trim($texte)), fn ($mot) => $mot !== '' && $mot !== '-'));
    }

    /**
     * Retire la première occurrence de $sousSequence (dans l'ordre, mais pas
     * forcément contiguë) de $tokens. Retourne null si un mot de la
     * sous-séquence est absent (le nom ne matche pas), sinon les tokens
     * restants (candidats prénom).
     *
     * @param  array<int, string>  $tokens
     * @param  array<int, string>  $sousSequence
     * @return array<int, string>|null
     */
    public static function sansSousSequence(array $tokens, array $sousSequence): ?array
    {
        $restants = $tokens;

        foreach ($sousSequence as $mot) {
            $position = array_search($mot, $restants, true);

            if ($position === false) {
                return null;
            }

            unset($restants[$position]);
        }

        return array_values($restants);
    }

    /**
     * Teste si $texteLibre contient le nom (en bloc) puis au moins un mot du
     * prénom. Utilitaire haut niveau pour les commandes de rapprochement.
     */
    public static function correspond(string $texteLibre, string $nom, string $prenom): bool
    {
        $restants = self::sansSousSequence(self::tokens($texteLibre), self::tokens($nom));

        if ($restants === null || $restants === []) {
            return false;
        }

        $prenomTokens = self::tokens($prenom);

        return $prenomTokens !== [] && array_intersect($restants, $prenomTokens) !== [];
    }
}
