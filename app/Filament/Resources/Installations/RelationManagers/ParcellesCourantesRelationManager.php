<?php

namespace App\Filament\Resources\Installations\RelationManagers;

use App\Models\AdresseVersion;
use App\Models\InstallationParcelleEvenement;
use App\Models\ParcelleVersion;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Affiche les liens actuellement actifs (projection). Lier/délier créent
 * toujours un nouvel évènement — jamais d'édition ni de suppression directe
 * de la projection.
 */
class ParcellesCourantesRelationManager extends RelationManager
{
    protected static string $relationship = 'parcellesCourantes';

    protected static ?string $title = 'Parcelles';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('parcelle_id')
            ->columns([
                TextColumn::make('parcelle_id')->label('Parcelle'),
                TextColumn::make('commune')
                    ->label('Commune / section')
                    ->getStateUsing(function ($record) {
                        $pv = ParcelleVersion::where('parcelle_id', $record->parcelle_id)->latest('id')->first();

                        return $pv ? "{$pv->commune_insee} / {$pv->section}{$pv->numero}" : '—';
                    }),
                TextColumn::make('surface')
                    ->label('Surface')
                    ->getStateUsing(function ($record) {
                        $pv = ParcelleVersion::where('parcelle_id', $record->parcelle_id)->latest('id')->first();

                        return $pv?->surface_m2 ? number_format($pv->surface_m2, 0, ',', ' ').' m²' : '—';
                    }),
                TextColumn::make('adresse_ban')
                    ->label('Adresse (BAN)')
                    ->getStateUsing(function ($record) {
                        $adresses = AdresseVersion::whereJsonContains('cad_parcelles', $record->parcelle_id)->get();

                        if ($adresses->isEmpty()) {
                            return '—';
                        }

                        return $adresses
                            ->map(fn (AdresseVersion $a) => trim("{$a->numero} {$a->nom_voie}").", {$a->code_postal} {$a->nom_commune}")
                            ->implode(' | ');
                    })
                    ->wrap(),
                TextColumn::make('confiance')->label('Confiance')->placeholder('—'),
                TextColumn::make('maj_le')->label('Depuis le')->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Action::make('lier')
                    ->label('Lier une parcelle')
                    ->icon('heroicon-o-link')
                    ->schema([
                        TextInput::make('parcelle_id')
                            ->label('Code parcelle (14 caractères)')
                            ->required()
                            ->maxLength(20)
                            ->exists(ParcelleVersion::class, 'parcelle_id')
                            ->validationMessages(['exists' => "Cette parcelle n'existe pas dans le référentiel cadastral importé."]),
                        TextInput::make('motif')
                            ->label('Motif')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data) {
                        InstallationParcelleEvenement::create([
                            'installation_id' => $this->getOwnerRecord()->id,
                            'parcelle_id' => $data['parcelle_id'],
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
                            ->label('Motif (ex : vente, erreur de saisie)')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (array $data, $record) {
                        InstallationParcelleEvenement::create([
                            'installation_id' => $this->getOwnerRecord()->id,
                            'parcelle_id' => $record->parcelle_id,
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
