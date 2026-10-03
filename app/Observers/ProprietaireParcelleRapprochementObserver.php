<?php

namespace App\Observers;

use App\Models\ProprietaireParcelleActuel;
use App\Models\ProprietaireParcelleRapprochement;

class ProprietaireParcelleRapprochementObserver
{
    public function created(ProprietaireParcelleRapprochement $rapprochement): void
    {
        ProprietaireParcelleActuel::updateOrCreate(
            [
                'proprietaire_version_id' => $rapprochement->proprietaire_version_id,
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
