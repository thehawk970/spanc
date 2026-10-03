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
                TextColumn::make('source')->label('Source')->badge()->searchable(),
                TextColumn::make('fichier_origine')->label('Fichier')->placeholder('—')->wrap()->toggleable(),
                TextColumn::make('statut')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'termine' => 'success',
                        'echec' => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('nombre_lignes')->label('Lignes')->numeric()->alignCenter(),
                TextColumn::make('declenchePar.name')->label('Déclenché par')->placeholder('Système'),
                TextColumn::make('demarre_le')->label('Démarré le')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('termine_le')->label('Terminé le')->dateTime('d/m/Y H:i')->placeholder('—'),
                TextColumn::make('commentaire')->label('Commentaire')->placeholder('—')->wrap()->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('source')
                    ->label('Source')
                    ->options(fn () => ImportBatch::query()->distinct()->pluck('source', 'source')->all()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
