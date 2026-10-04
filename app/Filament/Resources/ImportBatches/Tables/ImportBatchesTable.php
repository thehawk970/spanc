<?php

namespace App\Filament\Resources\ImportBatches\Tables;

use App\Models\ImportBatch;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ImportBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source')->label('Source')->badge()->searchable()->sortable(),
                TextColumn::make('fichier_origine')->label('Fichier')->placeholder('—')->wrap()->toggleable()->searchable()->sortable(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'termine' => 'success',
                        'echec' => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('nombre_lignes')->label('Lignes')->numeric()->alignCenter()->sortable(),
                TextColumn::make('declenchePar.name')->label('Déclenché par')->placeholder('Système')->searchable()->sortable(),
                TextColumn::make('demarre_le')->label('Démarré le')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('termine_le')->label('Terminé le')->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('commentaire')->label('Commentaire')->placeholder('—')->wrap()->toggleable()->searchable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('source')
                    ->label('Source')
                    ->options(fn () => ImportBatch::query()->distinct()->pluck('source', 'source')->all()),
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options(fn () => ImportBatch::query()->distinct()->pluck('statut', 'statut')->all()),
                SelectFilter::make('fichier_origine')
                    ->label('Fichier')
                    ->searchable()
                    ->options(fn () => ImportBatch::query()->whereNotNull('fichier_origine')->distinct()->pluck('fichier_origine', 'fichier_origine')),
                SelectFilter::make('declenche_par_id')
                    ->label('Déclenché par')
                    ->relationship('declenchePar', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
