<?php

namespace App\Filament\Resources\BatimentVersions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BatimentVersionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bâtiment')
                    ->columns(3)
                    ->components([
                        TextEntry::make('id')->label('ID'),
                        TextEntry::make('cle_dedup')->label('Clé de déduplication')->copyable(),
                        TextEntry::make('commune_insee')->label('Commune (INSEE)'),
                        TextEntry::make('type')->label('Type')->placeholder('—'),
                        TextEntry::make('nom')->label('Nom')->placeholder('—'),
                        TextEntry::make('centroide_lon')->label('Longitude')->placeholder('—'),
                        TextEntry::make('centroide_lat')->label('Latitude')->placeholder('—'),
                        TextEntry::make('importBatch.source')->label('Import')->badge(),
                        TextEntry::make('created_at')->label('Importé le')->dateTime('d/m/Y H:i'),
                    ]),
                Section::make('Géométrie (GeoJSON brut)')
                    ->collapsed()
                    ->components([
                        TextEntry::make('geometry')
                            ->label('')
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_UNESCAPED_UNICODE) : '—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
