<?php

namespace App\Filament\Resources\ControleVersions\Pages;

use App\Filament\Resources\ControleVersions\ControleVersionResource;
use Filament\Resources\Pages\ViewRecord;

/** Lecture seule : pas d'EditAction, les donnees sont append-only. */
class ViewControleVersion extends ViewRecord
{
    protected static string $resource = ControleVersionResource::class;
}
