<?php

namespace App\Observers;

use App\Models\BatimentParcelleActuel;
use App\Models\BatimentParcelleRapprochement;

class BatimentParcelleRapprochementObserver
{
    public function created(BatimentParcelleRapprochement $rapprochement): void
    {
        BatimentParcelleActuel::updateOrCreate(
            ['batiment_version_id' => $rapprochement->batiment_version_id],
            [
                'parcelle_version_id' => $rapprochement->parcelle_version_id,
                'confiance' => $rapprochement->confiance,
                'rapprochement_id' => $rapprochement->id,
                'maj_le' => $rapprochement->created_at,
            ]
        );
    }
}
