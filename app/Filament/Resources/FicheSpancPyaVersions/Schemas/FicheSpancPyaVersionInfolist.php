<?php

namespace App\Filament\Resources\FicheSpancPyaVersions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FicheSpancPyaVersionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Fiche')
                    ->columns(2)
                    ->components([
                        TextEntry::make('id_source')->label('ID source')->copyable(),
                        TextEntry::make('date_controle')->label('Date du contrôle')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('nature_dernier_controle')->label('Nature')->placeholder('—')->badge(),
                        TextEntry::make('avis')->label('Avis')->placeholder('—')->badge(),
                        TextEntry::make('conformite')->label('Conformité')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('created_at')->label('Importé le')->dateTime('d/m/Y H:i'),
                    ]),
                Section::make('Parcelle')
                    ->columns(2)
                    ->components([
                        TextEntry::make('ville_terrain_brute')->label('Commune (brute, source)')->placeholder('—'),
                        TextEntry::make('code_insee_resolu')->label('Commune (INSEE résolu)')->placeholder('Non reconnue'),
                        TextEntry::make('section_numero_brute')->label('Section/numéro (brut, source)')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('parcelle_ids')
                            ->label('Parcelles résolues')
                            ->getStateUsing(fn ($record) => $record->parcelle_ids ? implode(', ', $record->parcelle_ids) : 'Aucune')
                            ->columnSpanFull(),
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
