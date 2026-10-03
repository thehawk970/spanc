<?php

namespace App\Filament\Resources\Installations\RelationManagers;

use App\Models\InstallationCompteurEvenement;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompteursCourantsRelationManager extends RelationManager
{
    protected static string $relationship = 'compteursCourants';

    protected static ?string $title = 'Compteurs d\'eau';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('numero_compteur')
            ->columns([
                TextColumn::make('numero_compteur')->label('Numéro de compteur'),
                TextColumn::make('confiance')->label('Confiance')->placeholder('—'),
                TextColumn::make('maj_le')->label('Depuis le')->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Action::make('lier')
                    ->label('Lier un compteur')
                    ->icon('heroicon-o-link')
                    ->schema([
                        TextInput::make('numero_compteur')
                            ->label('Numéro de compteur (SOGEDO)')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('motif')
                            ->label('Motif')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data) {
                        InstallationCompteurEvenement::create([
                            'installation_id' => $this->getOwnerRecord()->id,
                            'numero_compteur' => $data['numero_compteur'],
                            'action' => 'lier',
                            'source' => 'manuel',
                            'motif' => $data['motif'] ?? null,
                            'auteur_id' => auth()->id(),
                        ]);
                    }),
            ])
            ->recordActions([
                Action::make('delier')
                    ->label('Délier')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        TextInput::make('motif')
                            ->label('Motif')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (array $data, $record) {
                        InstallationCompteurEvenement::create([
                            'installation_id' => $this->getOwnerRecord()->id,
                            'numero_compteur' => $record->numero_compteur,
                            'action' => 'delier',
                            'source' => 'manuel',
                            'motif' => $data['motif'],
                            'auteur_id' => auth()->id(),
                        ]);
                    }),
            ])
            ->toolbarActions([]);
    }
}
