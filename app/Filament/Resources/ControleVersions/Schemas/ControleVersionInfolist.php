<?php

namespace App\Filament\Resources\ControleVersions\Schemas;

use App\Filament\Resources\DispositifSpancVersions\DispositifSpancVersionResource;
use App\Models\ControleVersion;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ControleVersionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Contrôle')
                    ->columns(2)
                    ->components([
                        TextEntry::make('reference_controle')->label('Référence')->placeholder('—')->copyable(),
                        TextEntry::make('type_source')->label('Source')->badge(),
                        TextEntry::make('commune')->label('Commune')->placeholder('—'),
                        TextEntry::make('cadre_ou_type')->label('Cadre / Type')->placeholder('—'),
                        TextEntry::make('type_filiere')->label('Filière')->placeholder('—')->badge(),
                        TextEntry::make('date_visite')->label('Date de la visite')->date('d/m/Y')->placeholder('—'),
                        TextEntry::make('technicien')->label('Technicien')->placeholder('—'),
                        TextEntry::make('etat_controle')->label('État')->placeholder('—')->badge(),
                        TextEntry::make('avis')->label('Avis')->placeholder('—')->badge()->columnSpanFull(),
                    ]),
                Section::make('Personnes & adresses (source)')
                    ->columns(2)
                    ->components([
                        TextEntry::make('proprietaire_brut')->label('Propriétaire')->placeholder('—'),
                        TextEntry::make('usager_brut')->label('Usager')->placeholder('—'),
                        TextEntry::make('adresse_parcelle_brute')->label('Adresse parcelle')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('adresse_proprietaire_brute')->label('Adresse propriétaire')->placeholder('—')->columnSpanFull(),
                    ]),
                Section::make('Dossier dispositif lié')
                    ->components([
                        TextEntry::make('reference_dossier_liee')
                            ->label('Référence extraite')
                            ->placeholder('—'),
                        TextEntry::make('dispositif_lien')
                            ->label('Dispositif')
                            ->getStateUsing(fn (ControleVersion $record) => $record->dispositif ? 'Ouvrir le dossier →' : 'Aucun dispositif correspondant')
                            ->url(fn (ControleVersion $record) => $record->dispositif
                                ? DispositifSpancVersionResource::getUrl('view', ['record' => $record->dispositif->id])
                                : null),
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
