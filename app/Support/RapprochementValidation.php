<?php

namespace App\Support;

use App\Models\InstallationCompteurEvenement;
use App\Models\InstallationProprietaireEvenement;
use App\Models\RapprochementDecision;
use App\Models\RapprochementPropose;

/**
 * Logique de décision partagée entre la page Filament et la revue React :
 * valider crée le vrai lien (nouvel évènement, source=auto) + une décision ;
 * rejeter n'enregistre qu'une décision. La proposition elle-même n'est
 * jamais modifiée.
 */
class RapprochementValidation
{
    /** @return array{resultat: string, type: ?string, detail: ?string} */
    public static function valider(RapprochementPropose $record, int|string|null $auteurId, ?string $motifDecision = null): array
    {
        $determination = ['resultat' => 'sans_objet', 'type' => null, 'detail' => null];

        if ($record->type_cible === 'proprietaire' && $record->installation_id) {
            InstallationProprietaireEvenement::create([
                'installation_id' => $record->installation_id,
                'proprietaire_version_id' => $record->cible_id,
                'action' => 'lier',
                'source' => 'auto',
                'confiance' => $record->confiance,
                'motif' => "Rapprochement via parcelle commune ({$record->methode})",
                'auteur_id' => $auteurId,
            ]);
        }

        if ($record->type_cible === 'compteur' && $record->installation_id) {
            InstallationCompteurEvenement::create([
                'installation_id' => $record->installation_id,
                'numero_compteur' => $record->cible_id,
                'action' => 'lier',
                'source' => 'auto',
                'confiance' => $record->confiance,
                'motif' => "Rapprochement via parcelle commune ({$record->methode})",
                'auteur_id' => $auteurId,
            ]);

            $determination = DeterminationTypeSogedo::depuisCompteursLies($record->installation_id);
        }

        RapprochementDecision::create([
            'rapprochement_propose_id' => $record->id,
            'decision' => 'valide',
            'motif' => $motifDecision,
            'decide_par_id' => $auteurId,
        ]);

        return $determination;
    }

    public static function rejeter(RapprochementPropose $record, string $motif, int|string|null $auteurId): void
    {
        RapprochementDecision::create([
            'rapprochement_propose_id' => $record->id,
            'decision' => 'rejete',
            'motif' => $motif,
            'decide_par_id' => $auteurId,
        ]);
    }
}
