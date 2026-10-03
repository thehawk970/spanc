<?php

namespace App\Filament\Resources\AdresseVersions\Pages;

use App\Filament\Resources\AdresseVersions\AdresseVersionResource;
use Filament\Resources\Pages\ViewRecord;

/** Lecture seule : pas d'EditAction, les donnees sont append-only. */
class ViewAdresseVersion extends ViewRecord
{
    protected static string $resource = AdresseVersionResource::class;
}
