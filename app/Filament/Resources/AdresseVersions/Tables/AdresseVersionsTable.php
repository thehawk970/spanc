<?php

namespace App\Filament\Resources\AdresseVersions\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdresseVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')->label('N°')->placeholder('—'),
                TextColumn::make('nom_voie')->label('Voie')->searchable()->sortable(),
                TextColumn::make('code_postal')->label('CP'),
                TextColumn::make('code_insee')->label('Commune (INSEE)')->searchable(),
                TextColumn::make('nom_commune')->label('Commune'),
                TextColumn::make('cad_parcelles')
                    ->label('Parcelles cadastrales')
                    ->formatStateUsing(fn (?array $state) => $state ? implode(', ', $state) : '—')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('importBatch.source')->label('Import')->badge()->toggleable(),
                TextColumn::make('created_at')->label('Importée le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('nom_voie')
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
