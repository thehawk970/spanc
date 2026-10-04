<?php

namespace App\Filament\Resources\DispositifSpancVersions\Pages;

use App\Filament\Resources\DispositifSpancVersions\DispositifSpancVersionResource;
use Filament\Resources\Pages\ViewRecord;

/** Lecture seule : pas d'EditAction, les donnees sont append-only. */
class ViewDispositifSpancVersion extends ViewRecord
{
    protected static string $resource = DispositifSpancVersionResource::class;
}
