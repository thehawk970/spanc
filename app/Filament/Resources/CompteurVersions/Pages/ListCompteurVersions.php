<?php

namespace App\Filament\Resources\CompteurVersions\Pages;

use App\Filament\Resources\CompteurVersions\CompteurVersionResource;
use Filament\Resources\Pages\ListRecords;

/** Lecture seule : pas de creation depuis l'interface, alimente uniquement par les commandes d'import. */
class ListCompteurVersions extends ListRecords
{
    protected static string $resource = CompteurVersionResource::class;
}
