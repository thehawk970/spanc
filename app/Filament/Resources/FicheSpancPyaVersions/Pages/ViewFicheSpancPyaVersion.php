<?php

namespace App\Filament\Resources\FicheSpancPyaVersions\Pages;

use App\Filament\Resources\FicheSpancPyaVersions\FicheSpancPyaVersionResource;
use Filament\Resources\Pages\ViewRecord;

/** Lecture seule : pas d'EditAction, les donnees sont append-only. */
class ViewFicheSpancPyaVersion extends ViewRecord
{
    protected static string $resource = FicheSpancPyaVersionResource::class;
}
