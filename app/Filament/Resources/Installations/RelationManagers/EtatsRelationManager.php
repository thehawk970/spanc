<?php

namespace App\Filament\Resources\Installations\RelationManagers;

use App\Models\InstallationEtat;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Historique en lecture seule (aucune action d'édition/suppression : les
 * évènements sont immuables). "Nouvel état" ajoute une ligne, ne modifie
 * jamais les précédentes.
 */
class EtatsRelationManager extends RelationManager
{
    protected static string $relationship = 'etats';

    protected static ?string $title = 'Historique des états';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')->badge()->sortable(),
                TextColumn::make('statut')->badge()->sortable(),
                TextColumn::make('motif')->limit(40)->placeholder('—')->searchable(),
                TextColumn::make('auteur.name')->label('Auteur')->placeholder('—')->searchable()->sortable(),
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'non_determine' => 'Non déterminé',
                        'collectif' => 'Collectif',
                        'non_collectif' => 'Non collectif',
                    ]),
                SelectFilter::make('statut')
                    ->options([
                        'a_statuer' => 'À statuer',
                        'a_controler' => 'À contrôler',
                        'actif' => 'Actif',
                        'inactif' => 'Inactif',
                        'abandonne' => 'Abandonné',
                    ]),
            ])
            ->headerActions([
                Action::make('nouvelEtat')
                    ->label('Nouvel état')
                    ->icon('heroicon-o-plus')
                    ->schema([
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
                            ->required(),
                        KeyValue::make('metadata')
                            ->label('Métadonnées')
                            ->nullable(),
                        TextInput::make('motif')
                            ->label('Motif du changement')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data) {
                        InstallationEtat::create([
                            'installation_id' => $this->getOwnerRecord()->id,
                            'type' => $data['type'],
                            'statut' => $data['statut'],
                            'metadata' => $data['metadata'] ?? null,
                            'motif' => $data['motif'] ?? null,
                            'auteur_id' => auth()->id(),
                        ]);
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
