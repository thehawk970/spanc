<?php

namespace App\Filament\Resources\LogementHorsAssCollVersions\Pages;

use App\Filament\Resources\LogementHorsAssCollVersions\LogementHorsAssCollVersionResource;
use Filament\Resources\Pages\ListRecords;

/** Lecture seule : pas de creation depuis l'interface, alimente uniquement par cadastre:importer-logements-hors-ass-coll. */
class ListLogementHorsAssCollVersions extends ListRecords
{
    protected static string $resource = LogementHorsAssCollVersionResource::class;
}
