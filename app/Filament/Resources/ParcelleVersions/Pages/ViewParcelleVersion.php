<?php

namespace App\Filament\Resources\ParcelleVersions\Pages;

use App\Filament\Resources\ParcelleVersions\ParcelleVersionResource;
use Filament\Resources\Pages\ViewRecord;

/** Lecture seule : pas d'EditAction, les donnees sont append-only. */
class ViewParcelleVersion extends ViewRecord
{
    protected static string $resource = ParcelleVersionResource::class;
}
