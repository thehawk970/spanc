<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Installations\InstallationResource;
use App\Models\CompteurVersion;
use App\Models\InstallationCompteurEvenement;
use App\Models\InstallationProprietaireEvenement;
use App\Models\ProprietaireVersion;
use App\Models\RapprochementDecision;
use App\Models\RapprochementPropose;
use App\Support\DeterminationTypeSogedo;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
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
                        : null),
                TextColumn::make('type_cible')->label('Type')->badge(),
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
                TextColumn::make('methode')->label('Méthode'),
                TextColumn::make('confiance')->label('Confiance')->numeric(2)->sortable(),
                TextColumn::make('created_at')->label('Proposé le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('confiance', 'desc')
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
                                $resultat = $this->validerProposition(
                                    $record,
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
                        $resultat = $this->validerProposition($record);

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

    /** @return array{resultat: string, type: ?string, detail: ?string} */
    private function validerProposition(RapprochementPropose $record, ?string $motifDecision = null): array
    {
        $determination = ['resultat' => 'sans_objet', 'type' => null, 'detail' => null];

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

        if ($record->type_cible === 'compteur' && $record->installation_id) {
            InstallationCompteurEvenement::create([
                'installation_id' => $record->installation_id,
                'numero_compteur' => $record->cible_id,
                'action' => 'lier',
                'source' => 'auto',
                'confiance' => $record->confiance,
                'motif' => "Rapprochement via parcelle commune ({$record->methode})",
                'auteur_id' => auth()->id(),
            ]);

            $determination = DeterminationTypeSogedo::depuisCompteursLies($record->installation_id);
        }

        RapprochementDecision::create([
            'rapprochement_propose_id' => $record->id,
            'decision' => 'valide',
            'motif' => $motifDecision,
            'decide_par_id' => auth()->id(),
        ]);

        return $determination;
    }
}
