<?php

namespace App\Filament\Resources\Installations\Pages;

use App\Filament\Resources\Installations\InstallationResource;
use App\Models\Installation;
use App\Models\InstallationEtat;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateInstallation extends CreateRecord
{
    protected static string $resource = InstallationResource::class;

    /**
     * Le formulaire ne porte pas les colonnes d'Installation (elle n'en a
     * presque pas) mais celles de son premier état. On crée donc les deux
     * lignes ici plutot que de laisser Filament faire un create() générique.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $installation = Installation::create([
            'cree_par_id' => auth()->id(),
        ]);

        InstallationEtat::create([
            'installation_id' => $installation->id,
            'type' => $data['type'],
            'statut' => $data['statut'],
            'metadata' => $data['metadata'] ?? null,
            'motif' => $data['motif'] ?? null,
            'auteur_id' => auth()->id(),
        ]);

        return $installation;
    }
}
