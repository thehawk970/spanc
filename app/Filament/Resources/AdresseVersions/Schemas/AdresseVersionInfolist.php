<?php

namespace App\Filament\Resources\AdresseVersions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AdresseVersionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Adresse (BAN)')
                    ->columns(3)
                    ->components([
                        TextEntry::make('id_ban')->label('ID BAN')->copyable(),
                        TextEntry::make('numero')->label('Numéro')->placeholder('—'),
                        TextEntry::make('repetition')->label('Répétition')->placeholder('—'),
                        TextEntry::make('nom_voie')->label('Voie'),
                        TextEntry::make('code_postal')->label('Code postal'),
                        TextEntry::make('code_insee')->label('Commune (INSEE)'),
                        TextEntry::make('nom_commune')->label('Commune'),
                        TextEntry::make('lon')->label('Longitude')->placeholder('—'),
                        TextEntry::make('lat')->label('Latitude')->placeholder('—'),
                        TextEntry::make('cad_parcelles')
                            ->label('Parcelles cadastrales liées')
                            ->formatStateUsing(fn (?array $state) => $state ? implode(', ', $state) : 'Aucune')
                            ->columnSpanFull(),
                        TextEntry::make('importBatch.source')->label('Import')->badge(),
                        TextEntry::make('created_at')->label('Importée le')->dateTime('d/m/Y H:i'),
                    ]),
                Section::make('Propriétés brutes de la source')
                    ->collapsed()
                    ->components([
                        TextEntry::make('proprietes_brutes')
                            ->label('')
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
