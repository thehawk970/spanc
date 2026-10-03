<?php

namespace App\Filament\Resources\CompteurVersions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompteurVersionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Compteur')
                    ->columns(2)
                    ->components([
                        TextEntry::make('numero_compteur')->label('Numéro de compteur')->copyable(),
                        TextEntry::make('abonne_nom_brut')->label('Abonné (brut)')->placeholder('—'),
                        TextEntry::make('adresse_brute')->label('Adresse (brute)')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('importBatch.source')->label('Import')->badge(),
                        TextEntry::make('created_at')->label('Importé le')->dateTime('d/m/Y H:i'),
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
