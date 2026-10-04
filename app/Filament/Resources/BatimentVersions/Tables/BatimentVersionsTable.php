<?php

namespace App\Filament\Resources\BatimentVersions\Tables;

use App\Models\BatimentVersion;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BatimentVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('commune_insee')->label('Commune (INSEE)')->searchable()->sortable(),
                TextColumn::make('type')->label('Type')->placeholder('—')->searchable()->sortable(),
                TextColumn::make('nom')->label('Nom')->placeholder('—')->searchable()->sortable(),
                TextColumn::make('centroide_lon')->label('Lon.')->numeric(6)->sortable(),
                TextColumn::make('centroide_lat')->label('Lat.')->numeric(6)->sortable(),
                TextColumn::make('importBatch.source')->label('Import')->badge()->toggleable()->sortable(),
                TextColumn::make('created_at')->label('Importé le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('id')
            ->filters([
                SelectFilter::make('commune_insee')
                    ->label('Commune (INSEE)')
                    ->options(fn () => BatimentVersion::query()->distinct()->pluck('commune_insee', 'commune_insee')),
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(fn () => BatimentVersion::query()->distinct()->pluck('type', 'type')->filter()),
                SelectFilter::make('import_batch_id')
                    ->label('Import')
                    ->relationship('importBatch', 'source'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
