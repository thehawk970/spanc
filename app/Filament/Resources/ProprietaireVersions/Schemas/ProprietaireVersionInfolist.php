<?php

namespace App\Filament\Resources\ProprietaireVersions\Schemas;

use App\Models\ProprietaireVersion;
use App\Support\Adressage;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProprietaireVersionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Propriétaire')
                    ->columns(3)
                    ->components([
                        TextEntry::make('nom')->label('Nom'),
                        TextEntry::make('prenom')->label('Prénom')->placeholder('—'),
                        TextEntry::make('contact')->label('Contact')->placeholder('—'),
                        TextEntry::make('importBatch.source')->label('Source')->badge(),
                        TextEntry::make('created_at')->label('Importé / créé le')->dateTime('d/m/Y H:i'),
                    ]),
                Section::make('Adresses')
                    ->description('Le bien possédé et la résidence du propriétaire sont souvent deux endroits différents.')
                    ->columns(2)
                    ->components([
                        TextEntry::make('adresse_du_bien')
                            ->label('Adresse du bien (dans la communauté de communes)')
                            ->getStateUsing(fn (ProprietaireVersion $record) => Adressage::adresseDuBien($record->proprietes_brutes) ?? '—'),
                        TextEntry::make('adresse_correspondance')
                            ->label('Adresse de correspondance du propriétaire')
                            ->getStateUsing(fn (ProprietaireVersion $record) => Adressage::adresseCorrespondance($record->proprietes_brutes) ?? '—'),
                    ]),
                Section::make('Données brutes de la source')
                    ->collapsed()
                    ->components([
                        TextEntry::make('proprietes_brutes')
                            ->label('')
                            ->formatStateUsing(function ($state) {
                                $decoded = is_string($state) ? json_decode($state, true) : $state;

                                return $decoded ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : 'Aucune (saisie manuelle)';
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
