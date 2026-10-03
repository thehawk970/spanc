<?php

namespace App\Filament\Resources\ParcelleVersions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ParcelleVersionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Parcelle')
                    ->columns(3)
                    ->components([
                        TextEntry::make('parcelle_id')->label('Code parcelle')->copyable(),
                        TextEntry::make('commune_insee')->label('Commune (INSEE)'),
                        TextEntry::make('section')->label('Section'),
                        TextEntry::make('numero')->label('Numéro'),
                        TextEntry::make('surface_m2')->label('Surface')->suffix(' m²')->placeholder('—'),
                        TextEntry::make('importBatch.source')->label('Import')->badge(),
                        TextEntry::make('importBatch.fichier_origine')->label('Fichier source')->placeholder('—'),
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
                Section::make('Géométrie (GeoJSON brut)')
                    ->collapsed()
                    ->description("Repliée par défaut : potentiellement volumineuse (polygone complet).")
                    ->components([
                        TextEntry::make('geometry')
                            ->label('')
                            ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_UNESCAPED_UNICODE) : '—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
