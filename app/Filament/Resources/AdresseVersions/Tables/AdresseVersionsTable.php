<?php

namespace App\Filament\Resources\AdresseVersions\Tables;

use App\Models\AdresseVersion;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AdresseVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('numero')->label('N°')->placeholder('—')->searchable()->sortable(),
                TextColumn::make('nom_voie')->label('Voie')->searchable()->sortable(),
                TextColumn::make('code_postal')->label('CP')->searchable()->sortable(),
                TextColumn::make('code_insee')->label('Commune (INSEE)')->searchable()->sortable(),
                TextColumn::make('nom_commune')->label('Commune')->searchable()->sortable(),
                TextColumn::make('cad_parcelles')
                    ->label('Parcelles cadastrales')
                    ->formatStateUsing(fn (?array $state) => $state ? implode(', ', $state) : '—')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('importBatch.source')->label('Import')->badge()->toggleable()->sortable(),
                TextColumn::make('created_at')->label('Importée le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('nom_voie')
            ->filters([
                SelectFilter::make('numero')
                    ->label('N°')
                    ->searchable()
                    ->options(fn () => AdresseVersion::query()->whereNotNull('numero')->distinct()->pluck('numero', 'numero')),
                SelectFilter::make('nom_voie')
                    ->label('Voie')
                    ->searchable()
                    ->options(fn () => AdresseVersion::query()->distinct()->pluck('nom_voie', 'nom_voie')),
                SelectFilter::make('code_postal')
                    ->label('CP')
                    ->options(fn () => AdresseVersion::query()->whereNotNull('code_postal')->distinct()->pluck('code_postal', 'code_postal')),
                SelectFilter::make('code_insee')
                    ->label('Commune')
                    ->options(fn () => AdresseVersion::query()->distinct()->pluck('nom_commune', 'code_insee')->filter()),
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
