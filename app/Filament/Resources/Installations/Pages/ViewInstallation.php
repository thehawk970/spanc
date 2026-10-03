<?php

namespace App\Filament\Resources\Installations\Pages;

use App\Filament\Resources\Installations\InstallationResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * Pas d'EditAction : tout changement se fait via les relation managers
 * (nouvel état, lier/délier, ajouter un rapport), jamais une édition en place.
 */
class ViewInstallation extends ViewRecord
{
    protected static string $resource = InstallationResource::class;
}
