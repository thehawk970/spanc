<?php

namespace App\Filament\Resources\ParcelleVersions\Tables;

use App\Models\ParcelleVersion;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ParcelleVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('parcelle_id')->label('Code parcelle')->searchable()->sortable(),
                TextColumn::make('commune_insee')->label('Commune (INSEE)')->searchable()->sortable(),
                TextColumn::make('section')->label('Section')->searchable()->sortable(),
                TextColumn::make('numero')->label('Numéro')->searchable()->sortable(),
                TextColumn::make('surface_m2')->label('Surface')->numeric()->suffix(' m²')->sortable(),
                TextColumn::make('importBatch.source')->label('Import')->badge()->toggleable()->sortable(),
                TextColumn::make('created_at')->label('Importée le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('parcelle_id')
            ->filters([
                SelectFilter::make('commune_insee')
                    ->label('Commune (INSEE)')
                    ->options(fn () => ParcelleVersion::query()->distinct()->pluck('commune_insee', 'commune_insee')),
                SelectFilter::make('section')
                    ->label('Section')
                    ->searchable()
                    ->options(fn () => ParcelleVersion::query()->distinct()->pluck('section', 'section')->filter()),
                SelectFilter::make('numero')
                    ->label('Numéro')
                    ->searchable()
                    ->options(fn () => ParcelleVersion::query()->whereNotNull('numero')->distinct()->pluck('numero', 'numero')),
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
