<?php

namespace App\Filament\Resources\Installations\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Sert uniquement à la création : Installation n'a pas de champs propres
 * à éditer, donc ce formulaire porte en réalité l'état initial
 * (installation_etats) créé en même temps que l'installation.
 * Voir CreateInstallation::handleRecordCreation().
 */
class InstallationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('État initial')
                    ->description("Crée l'installation et son premier état en une seule étape.")
                    ->components([
                        Select::make('type')
                            ->label('Type')
                            ->options([
                                'non_determine' => 'Non déterminé',
                                'collectif' => 'Collectif',
                                'non_collectif' => 'Non collectif',
                            ])
                            ->required(),
                        Select::make('statut')
                            ->label('Statut')
                            ->options([
                                'a_statuer' => 'À statuer',
                                'a_controler' => 'À contrôler',
                                'actif' => 'Actif',
                                'inactif' => 'Inactif',
                                'abandonne' => 'Abandonné',
                            ])
                            ->default('a_controler')
                            ->required(),
                        KeyValue::make('metadata')
                            ->label('Métadonnées')
                            ->keyLabel('Clé')
                            ->valueLabel('Valeur')
                            ->addActionLabel('Ajouter une métadonnée')
                            ->nullable(),
                        TextInput::make('motif')
                            ->label('Motif / commentaire')
                            ->maxLength(255),
                    ]),
            ]);
    }
}
