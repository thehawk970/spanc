<?php

namespace App\Observers;

use App\Models\InstallationProprietaireCourante;
use App\Models\InstallationProprietaireEvenement;

class InstallationProprietaireEvenementObserver
{
    public function created(InstallationProprietaireEvenement $evenement): void
    {
        $cle = [
            'installation_id' => $evenement->installation_id,
            'proprietaire_version_id' => $evenement->proprietaire_version_id,
        ];

        if ($evenement->action === 'lier') {
            InstallationProprietaireCourante::updateOrCreate($cle, [
                'evenement_id' => $evenement->id,
                'maj_le' => $evenement->created_at,
            ]);

            return;
        }

        InstallationProprietaireCourante::where($cle)->delete();
    }
}
