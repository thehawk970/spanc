<?php

namespace App\Support;

use App\Models\CompteurVersion;
use App\Models\InstallationCompteurCourant;
use App\Models\InstallationEtat;
use App\Models\InstallationEtatCourant;

/**
 * Déduit collectif/non collectif depuis le code redevance SOGEDO (A9* = non
 * collectif, A1* = collectif) des compteurs liés à une installation. Centralisé
 * ici (plutôt que dans l'observer de projection) car ça crée un nouvel
 * évènement métier : l'observer est aussi rejoué tel quel par
 * projections:rebuild, où créer des évènements serait un effet de bord sur
 * le log source de vérité. Appelé explicitement à chaque liaison réelle
 * (manuelle ou validée), jamais pendant un rejeu.
 */
class DeterminationTypeSogedo
{
    /**
     * Ne tranche jamais en cas de codes contradictoires entre plusieurs
     * compteurs d'une même installation, et ne revient pas sur un type déjà
     * déterminé (manuellement ou précédemment).
     *
     * @return array{resultat: string, type: ?string, detail: ?string}
     */
    public static function depuisCompteursLies(string $installationId): array
    {
        $typeActuel = InstallationEtatCourant::where('installation_id', $installationId)->value('type');

        if ($typeActuel !== 'non_determine') {
            return ['resultat' => 'deja_determine', 'type' => null, 'detail' => null];
        }

        $numerosCompteurs = InstallationCompteurCourant::where('installation_id', $installationId)->pluck('numero_compteur');

        if ($numerosCompteurs->isEmpty()) {
            return ['resultat' => 'sans_compteur', 'type' => null, 'detail' => null];
        }

        $codes = CompteurVersion::query()
            ->whereIn('id', function ($sub) use ($numerosCompteurs) {
                $sub->selectRaw('MAX(id)')
                    ->from('compteur_versions')
                    ->whereIn('numero_compteur', $numerosCompteurs)
                    ->groupBy('numero_compteur');
            })
            ->get(['numero_compteur', 'proprietes_brutes'])
            ->mapWithKeys(fn ($c) => [$c->numero_compteur => $c->proprietes_brutes['Code Redevance 3'] ?? null])
            ->filter();

        if ($codes->isEmpty()) {
            return ['resultat' => 'sans_code', 'type' => null, 'detail' => null];
        }

        $types = $codes->map(fn ($code) => self::typeDepuisCode($code))->filter()->unique();

        if ($types->isEmpty()) {
            return ['resultat' => 'sans_code', 'type' => null, 'detail' => null];
        }

        if ($types->count() > 1) {
            return ['resultat' => 'ambigu', 'type' => null, 'detail' => null];
        }

        $detail = $codes->map(fn ($code, $numero) => "{$numero}:{$code}")->implode(', ');
        $type = $types->first();

        InstallationEtat::create([
            'installation_id' => $installationId,
            'type' => $type,
            'statut' => 'a_controler',
            'motif' => "Type deduit du code redevance SOGEDO des compteurs lies ({$detail})",
        ]);

        return ['resultat' => 'determine', 'type' => $type, 'detail' => $detail];
    }

    public static function typeDepuisCode(string $code): ?string
    {
        return match (true) {
            str_starts_with($code, 'A9') => 'non_collectif',
            str_starts_with($code, 'A1') => 'collectif',
            default => null,
        };
    }
}
