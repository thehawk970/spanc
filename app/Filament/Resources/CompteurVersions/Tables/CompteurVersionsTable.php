<?php

namespace App\Filament\Resources\CompteurVersions\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompteurVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero_compteur')->label('Numéro de compteur')->searchable()->sortable(),
                TextColumn::make('adresse_brute')->label('Adresse (brute)')->placeholder('—')->wrap(),
                TextColumn::make('abonne_nom_brut')->label('Abonné (brut)')->placeholder('—'),
                TextColumn::make('importBatch.source')->label('Import')->badge()->toggleable(),
                TextColumn::make('created_at')->label('Importé le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
