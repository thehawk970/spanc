<?php

namespace App\Filament\Resources\ProprietaireVersions\Tables;

use App\Models\ProprietaireVersion;
use App\Support\Adressage;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProprietaireVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nom')->label('Nom')->searchable()->sortable(),
                TextColumn::make('prenom')->label('Prénom')->searchable()->sortable()->placeholder('—'),
                TextColumn::make('contact')->label('Contact')->placeholder('—')->searchable()->sortable(),
                // Calculées depuis plusieurs colonnes brutes du CSV (adresse +
                // complément, ou adresse + commune + pays) : pas de tri/filtre
                // fiable sans une concaténation SQL fragile, laissées en
                // affichage seul.
                TextColumn::make('adresse_du_bien')
                    ->label('Adresse du bien')
                    ->getStateUsing(fn ($record) => Adressage::adresseDuBien($record->proprietes_brutes) ?? '—')
                    ->toggleable(),
                TextColumn::make('adresse_correspondance')
                    ->label('Adresse de correspondance')
                    ->getStateUsing(fn ($record) => Adressage::adresseCorrespondance($record->proprietes_brutes) ?? '—')
                    ->toggleable()
                    ->wrap(),
                TextColumn::make('importBatch.source')
                    ->label('Source')
                    ->badge()
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('created_at')->label('Importé le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('nom')
                    ->label('Nom')
                    ->searchable()
                    ->options(fn () => ProprietaireVersion::query()->distinct()->pluck('nom', 'nom')->filter()),
                SelectFilter::make('prenom')
                    ->label('Prénom')
                    ->searchable()
                    ->options(fn () => ProprietaireVersion::query()->whereNotNull('prenom')->distinct()->pluck('prenom', 'prenom')),
                SelectFilter::make('import_batch_id')
                    ->label('Source')
                    ->relationship('importBatch', 'source'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
