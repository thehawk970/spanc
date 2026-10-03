<?php

namespace App\Filament\Resources\ImportBatches\Pages;

use App\Filament\Resources\ImportBatches\ImportBatchResource;
use Filament\Resources\Pages\ListRecords;

/** Lecture seule : pas de creation depuis l'interface, alimente uniquement par les commandes d'import. */
class ListImportBatches extends ListRecords
{
    protected static string $resource = ImportBatchResource::class;
}
