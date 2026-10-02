<?php

namespace App\Observers;

use App\Models\InstallationBatimentCourant;
use App\Models\InstallationBatimentEvenement;

class InstallationBatimentEvenementObserver
{
    public function created(InstallationBatimentEvenement $evenement): void
    {
        $cle = [
            'installation_id' => $evenement->installation_id,
            'batiment_version_id' => $evenement->batiment_version_id,
        ];

        if ($evenement->action === 'lier') {
            InstallationBatimentCourant::updateOrCreate($cle, [
                'confiance' => $evenement->confiance,
                'evenement_id' => $evenement->id,
                'maj_le' => $evenement->created_at,
            ]);

            return;
        }

        InstallationBatimentCourant::where($cle)->delete();
    }
}
