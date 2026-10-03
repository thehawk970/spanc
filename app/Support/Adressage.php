<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalisation texte pour le rapprochement adresse propriétaire <-> BAN.
 * Volontairement simple (pas de Soundex/Levenshtein) : le but est un match
 * exact après normalisation, le reste restant une proposition à valider
 * humainement plutôt qu'un calcul de similarité approximatif de plus.
 */
class Adressage
{
    /** Majuscules, sans accents, ponctuation réduite à des espaces, espaces compactés. */
    public static function normaliser(string $texte): string
    {
        $texte = Str::ascii($texte);
        $texte = mb_strtoupper($texte);
        $texte = preg_replace('/[^A-Z0-9 ]/', ' ', $texte) ?? '';
        $texte = preg_replace('/\s+/', ' ', $texte) ?? '';

        return trim($texte);
    }

    /**
     * Sépare "24 Rue Jacques Manchotte" en numéro / répétition (bis, ter...) / voie.
     * Sans numéro détecté (lieu-dit), numero est null et voie = l'adresse entière.
     *
     * @return array{numero: ?string, repetition: ?string, voie: string}
     */
    public static function extraireNumeroVoie(string $adresse): array
    {
        $adresse = trim($adresse);

        if (preg_match('/^(\d+)([A-Za-z]{0,4})\s+(.*)$/', $adresse, $m)) {
            return [
                'numero' => $m[1],
                'repetition' => $m[2] !== '' ? $m[2] : null,
                'voie' => trim($m[3]),
            ];
        }

        return ['numero' => null, 'repetition' => null, 'voie' => $adresse];
    }

    /**
     * Adresse de correspondance du propriétaire (où il vit/reçoit son
     * courrier), à ne pas confondre avec l'adresse du bien : un propriétaire
     * peut résider ailleurs, y compris à l'étranger (vu en pratique : Suisse,
     * Royaume-Uni...). Construite depuis les colonnes brutes du CSV source.
     *
     * @param  array<string, mixed>|null  $proprietesBrutes
     */
    public static function adresseCorrespondance(?array $proprietesBrutes): ?string
    {
        if (! $proprietesBrutes) {
            return null;
        }

        $rue = trim(($proprietesBrutes['Adresse'] ?? '').' '.($proprietesBrutes['adresse2'] ?? ''));
        $communePro = trim($proprietesBrutes['commune pro'] ?? '');
        $pays = trim($proprietesBrutes['Pays'] ?? '');

        $parties = array_filter([$rue, $communePro, $pays]);

        return $parties === [] ? null : implode(', ', $parties);
    }

    /** Adresse du bien (dans le périmètre de l'EPCI), distincte de l'adresse de correspondance ci-dessus. */
    public static function adresseDuBien(?array $proprietesBrutes): ?string
    {
        $adresse = trim($proprietesBrutes['Points Adresses - base locale'] ?? '');

        return $adresse === '' ? null : $adresse;
    }
}
