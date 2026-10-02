<?php

namespace App\Observers;

use App\Models\InstallationParcelleCourante;
use App\Models\InstallationParcelleEvenement;

class InstallationParcelleEvenementObserver
{
    public function created(InstallationParcelleEvenement $evenement): void
    {
        $cle = [
            'installation_id' => $evenement->installation_id,
            'parcelle_id' => $evenement->parcelle_id,
        ];

        if ($evenement->action === 'lier') {
            InstallationParcelleCourante::updateOrCreate($cle, [
                'confiance' => $evenement->confiance,
                'evenement_id' => $evenement->id,
                'maj_le' => $evenement->created_at,
            ]);

            return;
        }

        InstallationParcelleCourante::where($cle)->delete();
    }
}
