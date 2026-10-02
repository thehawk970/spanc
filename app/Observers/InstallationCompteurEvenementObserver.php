<?php

namespace App\Observers;

use App\Models\InstallationCompteurCourant;
use App\Models\InstallationCompteurEvenement;

class InstallationCompteurEvenementObserver
{
    public function created(InstallationCompteurEvenement $evenement): void
    {
        $cle = [
            'installation_id' => $evenement->installation_id,
            'numero_compteur' => $evenement->numero_compteur,
        ];

        if ($evenement->action === 'lier') {
            InstallationCompteurCourant::updateOrCreate($cle, [
                'confiance' => $evenement->confiance,
                'evenement_id' => $evenement->id,
                'maj_le' => $evenement->created_at,
            ]);

            return;
        }

        InstallationCompteurCourant::where($cle)->delete();
    }
}
