<?php

namespace App\Filament\Resources\ParcelleVersions\Pages;

use App\Filament\Resources\ParcelleVersions\ParcelleVersionResource;
use Filament\Resources\Pages\ListRecords;

/** Lecture seule : pas de creation depuis l'interface, alimente uniquement par les commandes d'import. */
class ListParcelleVersions extends ListRecords
{
    protected static string $resource = ParcelleVersionResource::class;
}
