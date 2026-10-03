<?php

namespace App\Filament\Resources\ParcelleVersions\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ParcelleVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('parcelle_id')->label('Code parcelle')->searchable()->sortable(),
                TextColumn::make('commune_insee')->label('Commune (INSEE)')->searchable(),
                TextColumn::make('section')->label('Section'),
                TextColumn::make('numero')->label('Numéro'),
                TextColumn::make('surface_m2')->label('Surface')->numeric()->suffix(' m²')->sortable(),
                TextColumn::make('importBatch.source')->label('Import')->badge()->toggleable(),
                TextColumn::make('created_at')->label('Importée le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('parcelle_id')
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
