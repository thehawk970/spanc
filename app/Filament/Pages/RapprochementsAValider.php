<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Installations\InstallationResource;
use App\Models\InstallationProprietaireEvenement;
use App\Models\ProprietaireVersion;
use App\Models\RapprochementDecision;
use App\Models\RapprochementPropose;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/**
 * File de validation humaine des propositions de rapprochement (générées par
 * ex. par `installations:proposer-proprietaires`). Valider crée le vrai lien
 * (nouvel évènement, source=auto) + une décision ; rejeter n'enregistre
 * qu'une décision. La proposition elle-même n'est jamais modifiée.
 */
class RapprochementsAValider extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.rapprochements-a-valider';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static ?string $navigationLabel = 'Rapprochements à valider';

    protected static ?string $title = 'Rapprochements à valider';

    public function table(Table $table): Table
    {
        return $table
            ->query(RapprochementPropose::query()->whereDoesntHave('decisions'))
            ->emptyStateHeading('Aucun rapprochement en attente')
            ->columns([
                TextColumn::make('installation_id')
                    ->label('Installation')
                    ->formatStateUsing(fn (string $state) => mb_substr($state, 0, 8).'…')
                    ->url(fn (RapprochementPropose $record) => $record->installation_id
                        ? InstallationResource::getUrl('view', ['record' => $record->installation_id])
                        : null),
                TextColumn::make('type_cible')->label('Type')->badge(),
                TextColumn::make('cible')
                    ->label('Cible proposée')
                    ->getStateUsing(function (RapprochementPropose $record) {
                        if ($record->type_cible !== 'proprietaire') {
                            return $record->cible_id;
                        }

                        $proprietaire = ProprietaireVersion::find($record->cible_id);

                        return $proprietaire
                            ? trim("{$proprietaire->nom} {$proprietaire->prenom}")
                            : "#{$record->cible_id} (introuvable)";
                    }),
                TextColumn::make('methode')->label('Méthode'),
                TextColumn::make('confiance')->label('Confiance')->numeric(2)->sortable(),
                TextColumn::make('created_at')->label('Proposé le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('confiance', 'desc')
            ->recordActions([
                Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (RapprochementPropose $record) {
                        if ($record->type_cible === 'proprietaire' && $record->installation_id) {
                            InstallationProprietaireEvenement::create([
                                'installation_id' => $record->installation_id,
                                'proprietaire_version_id' => $record->cible_id,
                                'action' => 'lier',
                                'source' => 'auto',
                                'confiance' => $record->confiance,
                                'motif' => "Rapprochement via parcelle commune ({$record->methode})",
                                'auteur_id' => auth()->id(),
                            ]);
                        }

                        RapprochementDecision::create([
                            'rapprochement_propose_id' => $record->id,
                            'decision' => 'valide',
                            'decide_par_id' => auth()->id(),
                        ]);
                    }),
                Action::make('rejeter')
                    ->label('Rejeter')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        Textarea::make('motif')->label('Motif du rejet')->required(),
                    ])
                    ->action(function (array $data, RapprochementPropose $record) {
                        RapprochementDecision::create([
                            'rapprochement_propose_id' => $record->id,
                            'decision' => 'rejete',
                            'motif' => $data['motif'],
                            'decide_par_id' => auth()->id(),
                        ]);
                    }),
            ])
            ->toolbarActions([]);
    }
}
