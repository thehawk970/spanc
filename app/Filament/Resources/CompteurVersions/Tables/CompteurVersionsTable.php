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
            // Un compteur peut avoir ete importe plusieurs fois (correction
            // de donnees) : la liste ne montre que la derniere version de
            // chaque compteur, l'historique complet reste en base.
            ->modifyQueryUsing(fn ($query) => $query->whereIn('id', function ($sub) {
                $sub->selectRaw('MAX(id)')->from('compteur_versions')->groupBy('numero_compteur');
            }))
            ->columns([
                TextColumn::make('numero_compteur')->label('Numéro de compteur')->searchable()->sortable(),
                TextColumn::make('civilite')->label('Civilité')->placeholder('—'),
                TextColumn::make('abonne_nom_brut')->label('Abonné (brut)')->placeholder('—'),
                TextColumn::make('adresse_brute')->label('Adresse (brute)')->placeholder('—')->wrap(),
                TextColumn::make('code_redevance')
                    ->label('Code redevance')
                    ->getStateUsing(fn ($record) => $record->proprietes_brutes['Code Redevance 3'] ?? '—')
                    ->badge(),
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
