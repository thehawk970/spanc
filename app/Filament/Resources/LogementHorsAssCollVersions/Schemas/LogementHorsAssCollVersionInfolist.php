<?php

namespace App\Filament\Resources\LogementHorsAssCollVersions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LogementHorsAssCollVersionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Logement')
                    ->columns(2)
                    ->components([
                        TextEntry::make('parcelle_id')->label('Parcelle')->copyable(),
                        TextEntry::make('numero_proprietaire')->label('N° propriétaire')->placeholder('—')->copyable(),
                        TextEntry::make('proprietaire_nom_brut')->label('Propriétaire fiscal (brut)')->placeholder('—'),
                        TextEntry::make('type_habitation')->label('Type')->placeholder('—')->badge(),
                        TextEntry::make('nombre_locaux')->label('Nombre de locaux')->placeholder('—'),
                        TextEntry::make('importBatch.source')->label('Import')->badge(),
                        TextEntry::make('created_at')->label('Importé le')->dateTime('d/m/Y H:i'),
                    ]),
                Section::make('Données cadastrales / fiscales (source)')
                    ->columns(2)
                    ->components([
                        TextEntry::make('commune')
                            ->label('Commune (parcelle)')
                            ->getStateUsing(fn ($record) => $record->proprietes_brutes['Commune parcelle'] ?? '—'),
                        TextEntry::make('adresse_cadastre')
                            ->label('Adresse (cadastre, lieu-dit)')
                            ->getStateUsing(fn ($record) => $record->proprietes_brutes['Adresse parcelle (cadastre)'] ?? '—'),
                        TextEntry::make('zonage_collectif')
                            ->label('Dans un zonage d\'assainissement collectif')
                            ->getStateUsing(fn ($record) => $record->proprietes_brutes["A l'intérieur d'un zonage d'assainissement collectif"] ?? '—')
                            ->badge(),
                        TextEntry::make('commune_identique')
                            ->label('Propriétaire habite la même commune')
                            ->getStateUsing(fn ($record) => $record->proprietes_brutes['Commune identique'] ?? '—')
                            ->badge(),
                        TextEntry::make('interlocuteur_fiscal')
                            ->label('Interlocuteur fiscal')
                            ->getStateUsing(fn ($record) => $record->proprietes_brutes['Interlocuteur fiscal'] ?? '—')
                            ->columnSpanFull(),
                        TextEntry::make('adresse_fiscale')
                            ->label('Adresse fiscale')
                            ->getStateUsing(fn ($record) => $record->proprietes_brutes['Adresse fiscale'] ?? '—')
                            ->columnSpanFull(),
                        TextEntry::make('derniere_mutation')
                            ->label('Date de la dernière mutation')
                            ->getStateUsing(fn ($record) => $record->proprietes_brutes['Date de la dernière mutation'] ?? '—'),
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
