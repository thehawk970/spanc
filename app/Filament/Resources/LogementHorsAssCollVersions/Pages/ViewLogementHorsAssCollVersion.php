<?php

namespace App\Filament\Resources\LogementHorsAssCollVersions\Pages;

use App\Filament\Resources\LogementHorsAssCollVersions\LogementHorsAssCollVersionResource;
use Filament\Resources\Pages\ViewRecord;

/** Lecture seule : pas d'EditAction, les donnees sont append-only. */
class ViewLogementHorsAssCollVersion extends ViewRecord
{
    protected static string $resource = LogementHorsAssCollVersionResource::class;
}
