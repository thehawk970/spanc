<?php

namespace App\Filament\Resources\ProprietaireVersions\Tables;

use App\Support\Adressage;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProprietaireVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nom')->label('Nom')->searchable()->sortable(),
                TextColumn::make('prenom')->label('Prénom')->searchable()->placeholder('—'),
                TextColumn::make('contact')->label('Contact')->placeholder('—'),
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
                    ->toggleable(),
                TextColumn::make('created_at')->label('Importé le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
