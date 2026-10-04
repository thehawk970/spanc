<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Installations\InstallationResource;
use App\Models\CompteurVersion;
use App\Models\ProprietaireVersion;
use App\Models\RapprochementPropose;
use App\Support\RapprochementValidation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

/**
 * File de validation humaine des propositions de rapprochement (générées par
 * ex. par `installations:proposer-proprietaires`). Valider crée le vrai lien
 * (nouvel évènement, source=auto) + une décision ; rejeter n'enregistre
 * qu'une décision. La proposition elle-même n'est jamais modifiée.
 * "Tout valider" applique le même mécanisme à toute la file visible, y
 * compris les propositions à confiance réduite : à utiliser seulement après
 * avoir parcouru la liste, pas en remplacement systématique de la revue.
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
                        : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type_cible')->label('Type')->badge()->sortable(),
                TextColumn::make('cible')
                    ->label('Cible proposée')
                    ->getStateUsing(function (RapprochementPropose $record) {
                        if ($record->type_cible === 'proprietaire') {
                            $proprietaire = ProprietaireVersion::find($record->cible_id);

                            return $proprietaire
                                ? trim("{$proprietaire->nom} {$proprietaire->prenom}")
                                : "#{$record->cible_id} (introuvable)";
                        }

                        if ($record->type_cible === 'compteur') {
                            $compteur = CompteurVersion::where('numero_compteur', $record->cible_id)->latest('id')->first();

                            return $compteur
                                ? trim("{$compteur->civilite} {$compteur->abonne_nom_brut}")." — {$compteur->adresse_brute} (compteur {$record->cible_id})"
                                : "compteur {$record->cible_id} (introuvable)";
                        }

                        return $record->cible_id;
                    }),
                TextColumn::make('methode')->label('Méthode')->searchable()->sortable(),
                TextColumn::make('confiance')->label('Confiance')->numeric(2)->sortable(),
                TextColumn::make('created_at')->label('Proposé le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('confiance', 'desc')
            ->filters([
                SelectFilter::make('type_cible')
                    ->label('Type')
                    ->options([
                        'proprietaire' => 'Propriétaire',
                        'compteur' => 'Compteur',
                    ]),
                SelectFilter::make('methode')
                    ->label('Méthode')
                    ->options(fn () => RapprochementPropose::query()->distinct()->pluck('methode', 'methode')),
            ])
            ->headerActions([
                Action::make('tout_valider')
                    ->label('Tout valider')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Tout valider la file en attente ?')
                    ->modalDescription(fn () => $this->getFilteredTableQuery()->count()
                        .' proposition(s) seront validées en une fois, y compris celles à confiance réduite (parcelle partagée, adresse partielle). À utiliser après avoir vérifié la liste, pas à la place.')
                    ->action(function () {
                        $typesDetermines = 0;

                        DB::transaction(function () use (&$typesDetermines) {
                            foreach ($this->getFilteredTableQuery()->get() as $record) {
                                $resultat = RapprochementValidation::valider(
                                    $record,
                                    auth()->id(),
                                    'Validation manuelle groupée (bouton "Tout valider")'
                                );

                                if ($resultat['resultat'] === 'determine') {
                                    $typesDetermines++;
                                }
                            }
                        });

                        if ($typesDetermines > 0) {
                            Notification::make()
                                ->title("{$typesDetermines} type(s) AC/ANC déduit(s) au passage depuis le code redevance")
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->recordActions([
                Action::make('valider')
                    ->label('Valider')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (RapprochementPropose $record) {
                        $resultat = RapprochementValidation::valider($record, auth()->id());

                        if ($resultat['resultat'] === 'determine') {
                            Notification::make()
                                ->title('Type déduit : '.($resultat['type'] === 'collectif' ? 'Collectif' : 'Non collectif'))
                                ->body("Via le code redevance du compteur ({$resultat['detail']})")
                                ->success()
                                ->send();
                        }
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
                        RapprochementValidation::rejeter($record, $data['motif'], auth()->id());
                    }),
            ])
            ->toolbarActions([]);
    }
}
