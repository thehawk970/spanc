<?php

namespace App\Filament\Resources\ProprietaireVersions\Pages;

use App\Filament\Resources\ProprietaireVersions\ProprietaireVersionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProprietaireVersions extends ListRecords
{
    protected static string $resource = ProprietaireVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
