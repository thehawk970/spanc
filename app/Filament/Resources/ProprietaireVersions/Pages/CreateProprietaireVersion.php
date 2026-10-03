<?php

namespace App\Filament\Resources\ProprietaireVersions\Pages;

use App\Filament\Resources\ProprietaireVersions\ProprietaireVersionResource;
use App\Models\ImportBatch;
use Filament\Resources\Pages\CreateRecord;

class CreateProprietaireVersion extends CreateRecord
{
    protected static string $resource = ProprietaireVersionResource::class;

    /**
     * Chaque version a besoin d'un import_batch_id : une saisie manuelle
     * depuis cette page est un "batch d'une seule ligne", au même titre
     * qu'un import CSV.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $batch = ImportBatch::create([
            'source' => 'manuel',
            'statut' => 'termine',
            'nombre_lignes' => 1,
            'declenche_par_id' => auth()->id(),
            'demarre_le' => now(),
            'termine_le' => now(),
            'commentaire' => 'Saisie manuelle via Filament',
        ]);

        $data['import_batch_id'] = $batch->id;

        return $data;
    }
}
