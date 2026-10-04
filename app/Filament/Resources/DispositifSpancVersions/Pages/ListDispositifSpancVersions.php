<?php

namespace App\Filament\Resources\DispositifSpancVersions\Pages;

use App\Filament\Resources\DispositifSpancVersions\DispositifSpancVersionResource;
use Filament\Resources\Pages\ListRecords;

/** Lecture seule : pas de creation depuis l'interface, alimente uniquement par cadastre:importer-dispositifs-spanc. */
class ListDispositifSpancVersions extends ListRecords
{
    protected static string $resource = DispositifSpancVersionResource::class;
}
