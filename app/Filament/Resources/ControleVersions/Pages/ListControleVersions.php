<?php

namespace App\Filament\Resources\ControleVersions\Pages;

use App\Filament\Resources\ControleVersions\ControleVersionResource;
use Filament\Resources\Pages\ListRecords;

/** Lecture seule : pas de creation depuis l'interface, alimente uniquement par cadastre:importer-controles. */
class ListControleVersions extends ListRecords
{
    protected static string $resource = ControleVersionResource::class;
}
