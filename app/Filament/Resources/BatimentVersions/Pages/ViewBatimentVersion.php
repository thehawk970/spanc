<?php

namespace App\Filament\Resources\BatimentVersions\Pages;

use App\Filament\Resources\BatimentVersions\BatimentVersionResource;
use Filament\Resources\Pages\ViewRecord;

/** Lecture seule : pas d'EditAction, les donnees sont append-only. */
class ViewBatimentVersion extends ViewRecord
{
    protected static string $resource = BatimentVersionResource::class;
}
