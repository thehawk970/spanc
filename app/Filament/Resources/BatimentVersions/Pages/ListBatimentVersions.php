<?php

namespace App\Filament\Resources\BatimentVersions\Pages;

use App\Filament\Resources\BatimentVersions\BatimentVersionResource;
use Filament\Resources\Pages\ListRecords;

/** Lecture seule : pas de creation depuis l'interface, alimente uniquement par les commandes d'import. */
class ListBatimentVersions extends ListRecords
{
    protected static string $resource = BatimentVersionResource::class;
}
