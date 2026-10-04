<?php

namespace App\Filament\Resources\Installations\RelationManagers;

use App\Models\BatimentVersion;
use App\Models\InstallationBatimentEvenement;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Pas de clé métier stable côté bâtiment (contrairement à la parcelle) :
 * on référence une version précise (batiment_version_id), recherchée par
 * commune ou identifiant technique faute d'adresse disponible pour l'instant.
 */
class BatimentsCourantsRelationManager extends RelationManager
{
    protected static string $relationship = 'batimentsCourants';

    protected static ?string $title = 'Bâtiments';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('batiment_version_id')
            ->columns([
                TextColumn::make('batimentVersion.id')->label('ID technique')->searchable()->sortable(),
                TextColumn::make('batimentVersion.commune_insee')->label('Commune (INSEE)')->searchable()->sortable(),
                TextColumn::make('confiance')->label('Confiance')->placeholder('—')->sortable(),
                TextColumn::make('maj_le')->label('Depuis le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->headerActions([
                Action::make('lier')
                    ->label('Lier un bâtiment')
                    ->icon('heroicon-o-link')
                    ->schema([
                        Select::make('batiment_version_id')
                            ->label('Bâtiment')
                            ->helperText("Recherche par identifiant technique ou code commune INSEE (pas d'adresse disponible pour l'instant).")
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => BatimentVersion::query()
                                ->where('id', 'like', "%{$search}%")
                                ->orWhere('commune_insee', 'like', "%{$search}%")
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (BatimentVersion $b) => [$b->id => "#{$b->id} — commune {$b->commune_insee}"]))
                            ->getOptionLabelUsing(fn ($value) => $value ? "#{$value}" : null)
                            ->required(),
                        TextInput::make('motif')
                            ->label('Motif')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data) {
                        InstallationBatimentEvenement::create([
                            'installation_id' => $this->getOwnerRecord()->id,
                            'batiment_version_id' => $data['batiment_version_id'],
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
                        InstallationBatimentEvenement::create([
                            'installation_id' => $this->getOwnerRecord()->id,
                            'batiment_version_id' => $record->batiment_version_id,
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
