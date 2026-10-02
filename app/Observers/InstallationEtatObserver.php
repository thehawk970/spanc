<?php

namespace App\Observers;

use App\Models\InstallationEtat;
use App\Models\InstallationEtatCourant;

class InstallationEtatObserver
{
    public function created(InstallationEtat $etat): void
    {
        InstallationEtatCourant::updateOrCreate(
            ['installation_id' => $etat->installation_id],
            [
                'type' => $etat->type,
                'statut' => $etat->statut,
                'metadata' => $etat->metadata,
                'evenement_id' => $etat->id,
                'maj_le' => $etat->created_at,
            ]
        );
    }
}
