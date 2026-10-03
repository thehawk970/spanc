<?php

namespace App\Filament\Resources\CompteurVersions\Pages;

use App\Filament\Resources\CompteurVersions\CompteurVersionResource;
use Filament\Resources\Pages\ViewRecord;

/** Lecture seule : pas d'EditAction, les donnees sont append-only. */
class ViewCompteurVersion extends ViewRecord
{
    protected static string $resource = CompteurVersionResource::class;
}
