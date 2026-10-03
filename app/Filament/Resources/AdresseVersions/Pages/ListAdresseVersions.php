<?php

namespace App\Filament\Resources\AdresseVersions\Pages;

use App\Filament\Resources\AdresseVersions\AdresseVersionResource;
use Filament\Resources\Pages\ListRecords;

/** Lecture seule : pas de creation depuis l'interface, alimente uniquement par les commandes d'import. */
class ListAdresseVersions extends ListRecords
{
    protected static string $resource = AdresseVersionResource::class;
}
