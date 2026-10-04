<?php

namespace App\Filament\Resources\DispositifSpancVersions\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DispositifSpancVersionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dossier')
                    ->columns(2)
                    ->components([
                        TextEntry::make('reference_dossier')->label('Dossier')->copyable(),
                        TextEntry::make('code_insee')->label('Commune (INSEE)')->placeholder('—'),
                        TextEntry::make('section')->label('Section')->placeholder('—'),
                        TextEntry::make('numero')->label('Numéro')->placeholder('—'),
                        TextEntry::make('type_filiere')->label('Filière')->placeholder('—')->badge(),
                        TextEntry::make('created_at')->label('Importé le')->dateTime('d/m/Y H:i'),
                    ]),
                Section::make('Dernier contrôle connu')
                    ->columns(2)
                    ->components([
                        TextEntry::make('date_derniere_visite')->label('Date de la visite')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('technicien')->label('Technicien')->placeholder('—'),
                        TextEntry::make('nature_dernier_controle')->label('Nature')->placeholder('—')->badge(),
                        TextEntry::make('avis_dernier_controle')->label('Avis')->placeholder('—')->badge(),
                    ]),
                Section::make('Propriétaire')
                    ->columns(2)
                    ->components([
                        TextEntry::make('nom_cadastre')->label('Nom (source cadastre)')->placeholder('—'),
                        TextEntry::make('prenom_cadastre')->label('Prénom (source cadastre)')->placeholder('—'),
                        TextEntry::make('nom_spanc')->label('Nom (source SPANC)')->placeholder('—'),
                        TextEntry::make('prenom_spanc')->label('Prénom (source SPANC)')->placeholder('—'),
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
