<?php

namespace App\Filament\Resources\Installations\Tables;

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
                ->with(['etatCourant', 'proprietairesCourants.proprietaireVersion'])
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
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('etatCourant.type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'collectif' => 'warning',
                        'non_collectif' => 'success',
                        'non_determine' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('etatCourant.statut')
                    ->label('Statut')
                    ->badge(),
                TextColumn::make('parcelles_courantes_count')
                    ->label('Parcelles')
                    ->alignCenter(),
                TextColumn::make('batiments_courants_count')
                    ->label('Bâtiments')
                    ->alignCenter(),
                TextColumn::make('compteurs_courants_count')
                    ->label('Compteurs')
                    ->alignCenter(),
                TextColumn::make('proprietaires_courants_count')
                    ->label('Propriétaires')
                    ->alignCenter(),
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
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
