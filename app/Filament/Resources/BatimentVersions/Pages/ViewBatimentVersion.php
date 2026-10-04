<?php

namespace App\Filament\Resources\BatimentVersions\Pages;

use App\Filament\Resources\BatimentVersions\BatimentVersionResource;
use App\Filament\Resources\Installations\InstallationResource;
use App\Models\Installation;
use App\Models\InstallationBatimentEvenement;
use App\Models\InstallationEtat;
use App\Models\InstallationParcelleEvenement;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

/** Lecture seule : pas d'EditAction, les donnees sont append-only. */
class ViewBatimentVersion extends ViewRecord
{
    protected static string $resource = BatimentVersionResource::class;

    /**
     * Conversion manuelle d'un bâtiment non référencé en installation
     * indépendante : typiquement déclenché lors d'une visite terrain,
     * notamment pour séparer deux installations qui partageraient un seul
     * compteur d'eau (le bootstrap automatique par adresse n'en crée
     * qu'une). Action custom plutôt que CreateAction, même raison que
     * RapportsRelationManager::ajouterRapport : CreateAction masque
     * silencieusement le bouton tant qu'aucune Policy n'est enregistrée.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('creer_installation')
                ->label('Créer une installation à partir de ce bâtiment')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->visible(fn () => ! $this->record->installationCourante && $this->record->parcelleActuelle !== null)
                ->requiresConfirmation()
                ->modalHeading('Créer une nouvelle installation ?')
                ->modalDescription("Crée une installation indépendante, rattachée à la parcelle déjà rapprochée de ce bâtiment. À utiliser lors d'une visite terrain — notamment pour séparer plusieurs installations qui partageraient un seul compteur d'eau, plutôt que de les fusionner.")
                ->action(function () {
                    $batiment = $this->record;
                    $parcelleId = $batiment->parcelleActuelle->parcelleVersion->parcelle_id;

                    $installation = DB::transaction(function () use ($batiment, $parcelleId) {
                        $installation = Installation::create(['cree_par_id' => auth()->id()]);

                        InstallationEtat::create([
                            'installation_id' => $installation->id,
                            'type' => 'non_determine',
                            'statut' => 'a_statuer',
                            'motif' => 'Creation manuelle depuis batiment non reference, visite terrain',
                            'auteur_id' => auth()->id(),
                        ]);

                        InstallationParcelleEvenement::create([
                            'installation_id' => $installation->id,
                            'parcelle_id' => $parcelleId,
                            'action' => 'lier',
                            'source' => 'manuel',
                            'confiance' => 1.0,
                            'auteur_id' => auth()->id(),
                        ]);

                        InstallationBatimentEvenement::create([
                            'installation_id' => $installation->id,
                            'batiment_version_id' => $batiment->id,
                            'action' => 'lier',
                            'source' => 'manuel',
                            'confiance' => 1.0,
                            'auteur_id' => auth()->id(),
                        ]);

                        return $installation;
                    });

                    Notification::make()
                        ->title('Installation créée')
                        ->success()
                        ->send();

                    $this->redirect(InstallationResource::getUrl('view', ['record' => $installation->id]));
                }),
        ];
    }
}
