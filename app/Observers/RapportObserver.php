<?php

namespace App\Observers;

use App\Models\InstallationRapportCourant;
use App\Models\Rapport;

/**
 * Contrairement aux autres observers de projection (qui prennent toujours
 * le dernier évènement créé), les rapports sont régulièrement importés en
 * masse dans un ordre qui n'est pas chronologique : la projection ne doit
 * refléter que le rapport le plus RÉCENT PAR DATE, pas le dernier inséré.
 * Correct aussi bien en écriture directe qu'au rejeu complet
 * (projections:rebuild rejoue par ordre d'id, pas par date).
 */
class RapportObserver
{
    public function created(Rapport $rapport): void
    {
        $actuel = InstallationRapportCourant::find($rapport->installation_id);

        if ($actuel && $actuel->date_controle->greaterThan($rapport->date_controle)) {
            return;
        }

        InstallationRapportCourant::updateOrCreate(
            ['installation_id' => $rapport->installation_id],
            [
                'rapport_id' => $rapport->id,
                'type_controle' => $rapport->type_controle,
                'date_controle' => $rapport->date_controle,
                'conclusion' => $rapport->conclusion,
                'maj_le' => $rapport->created_at,
            ]
        );
    }
}
