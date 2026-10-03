<?php

namespace App\Filament\Resources\Installations\RelationManagers;

use App\Models\Rapport;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Un rapport est déjà par nature un fait daté et immuable : contrairement
 * aux liens parcelle/bâtiment/compteur/propriétaire, "créer" suffit (pas de
 * notion de lier/délier). Toujours pas d'édition ni de suppression.
 *
 * Action custom (pas CreateAction) : CreateAction applique une vérification
 * d'autorisation basée policy qui masque silencieusement le bouton tant
 * qu'aucune RapportPolicy n'est enregistrée.
 */
class RapportsRelationManager extends RelationManager
{
    protected static string $relationship = 'rapports';

    protected static ?string $title = 'Rapports';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type_controle')
            ->defaultSort('date_controle', 'desc')
            ->columns([
                TextColumn::make('type_controle')->label('Type')->badge(),
                TextColumn::make('date_controle')->label('Date')->date('d/m/Y'),
                TextColumn::make('conclusion')->label('Conclusion')->badge()->placeholder('—'),
                TextColumn::make('commentaire')->label('Commentaire')->limit(50)->placeholder('—'),
                TextColumn::make('auteur.name')->label('Auteur')->placeholder('—'),
            ])
            ->headerActions([
                Action::make('ajouterRapport')
                    ->label('Ajouter un rapport')
                    ->icon('heroicon-o-plus')
                    ->schema([
                        Select::make('type_controle')
                            ->label('Type de contrôle')
                            ->options([
                                'diagnostic_initial' => 'Diagnostic initial',
                                'controle_periodique' => 'Contrôle périodique',
                                'controle_conception' => 'Contrôle de conception',
                                'controle_realisation' => 'Contrôle de réalisation',
                                'controle_vente' => 'Contrôle (vente immobilière)',
                            ])
                            ->required(),
                        DatePicker::make('date_controle')
                            ->label('Date du contrôle')
                            ->required()
                            ->default(now()),
                        Select::make('conclusion')
                            ->label('Conclusion')
                            ->options([
                                'conforme' => 'Conforme',
                                'non_conforme' => 'Non conforme',
                                'avec_reserves' => 'Avec réserves',
                            ]),
                        Textarea::make('commentaire')
                            ->label('Commentaire')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data) {
                        Rapport::create([
                            ...$data,
                            'installation_id' => $this->getOwnerRecord()->id,
                            'auteur_id' => auth()->id(),
                        ]);
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
