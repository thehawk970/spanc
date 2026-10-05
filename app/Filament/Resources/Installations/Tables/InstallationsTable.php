<?php

namespace App\Filament\Resources\Installations\Tables;

use App\Models\AdresseVersion;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InstallationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['etatCourant', 'rapportCourant', 'parcellesCourantes', 'proprietairesCourants.proprietaireVersion'])
                ->withCount(['parcellesCourantes', 'batimentsCourants', 'compteursCourants', 'proprietairesCourants']))
            ->columns([
                TextColumn::make('proprietaires')
                    ->label('Propriétaire(s)')
                    ->getStateUsing(function ($record) {
                        $noms = $record->proprietairesCourants
                            ->map(fn ($lien) => trim("{$lien->proprietaireVersion?->nom} {$lien->proprietaireVersion?->prenom}"))
                            ->filter();

                        return $noms->isEmpty() ? 'Propriétaire inconnu' : $noms->implode(', ');
                    })
                    ->wrap(),
                TextColumn::make('id')
                    ->label('ID')
                    ->copyable()
                    ->formatStateUsing(fn (string $state) => mb_substr($state, 0, 8).'…')
                    ->tooltip(fn ($record) => $record->id)
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('etatCourant.type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'collectif' => 'warning',
                        'non_collectif' => 'success',
                        'non_determine' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('etatCourant.statut')
                    ->label('Statut')
                    ->badge()
                    ->sortable(),
                TextColumn::make('rapportCourant.date_controle')
                    ->label('Dernier rapport')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('derniere_conclusion')
                    ->label('Dernière conclusion')
                    ->getStateUsing(function ($record) {
                        if (! $record->rapportCourant) {
                            return null;
                        }

                        return match ($record->rapportCourant->conclusion) {
                            'conforme' => 'Conforme',
                            'non_conforme' => 'Non conforme',
                            'avec_reserves' => 'Avec réserves',
                            default => 'Autre / non renseigné',
                        };
                    })
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'Conforme' => 'success',
                        'Non conforme' => 'danger',
                        'Avec réserves' => 'warning',
                        default => 'gray',
                    })
                    ->placeholder('—'),
                TextColumn::make('code_insee')
                    ->label('Code INSEE')
                    ->getStateUsing(fn ($record) => $record->communeActuelle()['code_insee'] ?? '—'),
                TextColumn::make('commune')
                    ->label('Commune')
                    ->getStateUsing(fn ($record) => $record->communeActuelle()['nom'] ?? '—'),
                TextColumn::make('parcelles_courantes_count')
                    ->label('Parcelles')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('batiments_courants_count')
                    ->label('Bâtiments')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('compteurs_courants_count')
                    ->label('Compteurs')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('proprietaires_courants_count')
                    ->label('Propriétaires')
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'non_determine' => 'Non déterminé',
                        'collectif' => 'Collectif',
                        'non_collectif' => 'Non collectif',
                    ])
                    ->query(fn ($query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($query, $value) => $query->whereHas('etatCourant', fn ($q) => $q->where('type', $value))
                    )),
                SelectFilter::make('statut')
                    ->label('Statut')
                    ->options([
                        'a_statuer' => 'À statuer',
                        'a_controler' => 'À contrôler',
                        'actif' => 'Actif',
                        'inactif' => 'Inactif',
                        'abandonne' => 'Abandonné',
                    ])
                    ->query(fn ($query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($query, $value) => $query->whereHas('etatCourant', fn ($q) => $q->where('statut', $value))
                    )),
                SelectFilter::make('derniere_conclusion')
                    ->label('Dernière conclusion')
                    ->options([
                        'conforme' => 'Conforme',
                        'non_conforme' => 'Non conforme',
                        'avec_reserves' => 'Avec réserves',
                    ])
                    ->query(fn ($query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($query, $value) => $query->whereHas('rapportCourant', fn ($q) => $q->where('conclusion', $value))
                    )),
                SelectFilter::make('commune_insee')
                    ->label('Commune')
                    ->options(fn () => AdresseVersion::query()->distinct()->pluck('nom_commune', 'code_insee')->filter())
                    ->query(fn ($query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($query, $value) => $query->whereHas('parcellesCourantes', function ($q) use ($value) {
                            $q->whereIn('parcelle_id', function ($sub) use ($value) {
                                $sub->select('parcelle_id')->from('parcelle_versions')->where('commune_insee', $value);
                            });
                        })
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
