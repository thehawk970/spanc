<?php

namespace App\Filament\Resources\ProprietaireVersions\Pages;

use App\Filament\Resources\ProprietaireVersions\ProprietaireVersionResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * Pas d'EditAction : un propriétaire est une version append-only, elle ne
 * se corrige jamais en place.
 */
class ViewProprietaireVersion extends ViewRecord
{
    protected static string $resource = ProprietaireVersionResource::class;
}
