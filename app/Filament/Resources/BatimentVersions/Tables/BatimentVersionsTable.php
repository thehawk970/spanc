<?php

namespace App\Filament\Resources\BatimentVersions\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BatimentVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('commune_insee')->label('Commune (INSEE)')->searchable()->sortable(),
                TextColumn::make('type')->label('Type')->placeholder('—'),
                TextColumn::make('nom')->label('Nom')->placeholder('—'),
                TextColumn::make('centroide_lon')->label('Lon.')->numeric(6),
                TextColumn::make('centroide_lat')->label('Lat.')->numeric(6),
                TextColumn::make('importBatch.source')->label('Import')->badge()->toggleable(),
                TextColumn::make('created_at')->label('Importé le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('id')
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
