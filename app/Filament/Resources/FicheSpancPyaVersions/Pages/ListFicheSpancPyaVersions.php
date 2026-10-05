<?php

namespace App\Filament\Resources\FicheSpancPyaVersions\Pages;

use App\Filament\Resources\FicheSpancPyaVersions\FicheSpancPyaVersionResource;
use Filament\Resources\Pages\ListRecords;

/** Lecture seule : pas de creation depuis l'interface, alimente uniquement par cadastre:importer-fiches-spanc-pya. */
class ListFicheSpancPyaVersions extends ListRecords
{
    protected static string $resource = FicheSpancPyaVersionResource::class;
}
