<?php

namespace App\Observers;

use App\Models\CompteurParcelleActuel;
use App\Models\CompteurParcelleRapprochement;

class CompteurParcelleRapprochementObserver
{
    public function created(CompteurParcelleRapprochement $rapprochement): void
    {
        CompteurParcelleActuel::updateOrCreate(
            [
                'numero_compteur' => $rapprochement->numero_compteur,
                'parcelle_id' => $rapprochement->parcelle_id,
            ],
            [
                'confiance' => $rapprochement->confiance,
                'rapprochement_id' => $rapprochement->id,
                'maj_le' => $rapprochement->created_at,
            ]
        );
    }
}
