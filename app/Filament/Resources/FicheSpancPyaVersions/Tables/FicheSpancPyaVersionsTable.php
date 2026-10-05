<?php

namespace App\Filament\Resources\FicheSpancPyaVersions\Tables;

use App\Filament\Resources\Installations\InstallationResource;
use App\Models\FicheSpancPyaVersion;
use App\Models\InstallationParcelleCourante;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class FicheSpancPyaVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id_source')->label('ID source')->searchable()->sortable(),
                TextColumn::make('ville_terrain_brute')->label('Commune (brute)')->placeholder('—')->searchable()->sortable(),
                IconColumn::make('commune_reconnue')
                    ->label('Commune reconnue')
                    ->boolean()
                    ->getStateUsing(fn (FicheSpancPyaVersion $record) => $record->code_insee_resolu !== null),
                TextColumn::make('section_numero_brute')->label('Parcelle(s) (brute)')->placeholder('—')->wrap()->searchable(),
                TextColumn::make('parcelle_ids')
                    ->label('Parcelles résolues')
                    ->getStateUsing(fn (FicheSpancPyaVersion $record) => $record->parcelle_ids ? implode(', ', $record->parcelle_ids) : '—')
                    ->wrap(),
                TextColumn::make('installation')
                    ->label('Installation')
                    ->getStateUsing(function (FicheSpancPyaVersion $record) {
                        $ids = self::installationIds($record);

                        return match (true) {
                            $ids->isEmpty() => 'Aucune',
                            $ids->count() === 1 => mb_substr($ids->first(), 0, 8).'…',
                            default => "{$ids->count()} installations",
                        };
                    })
                    ->url(function (FicheSpancPyaVersion $record) {
                        $premiere = self::installationIds($record)->first();

                        return $premiere ? InstallationResource::getUrl('view', ['record' => $premiere]) : null;
                    })
                    ->openUrlInNewTab(),
                TextColumn::make('proprietaire_brut')
                    ->label('Propriétaire (brut, source)')
                    ->getStateUsing(function (FicheSpancPyaVersion $record) {
                        $brut = $record->proprietes_brutes ?? [];
                        $nom = trim(($brut['Titre du propriétaire'] ?? '').' '.($brut['Nom du propriétaire'] ?? '').' '.($brut['Prénom du propriétaire'] ?? ''));

                        return $nom !== '' ? $nom : null;
                    })
                    ->placeholder('—')
                    ->tooltip('Information brute de la source, non fiable pour le rapprochement (fiches parfois anciennes, propriétaire possiblement change depuis) — lien par parcelle uniquement.')
                    ->toggleable(),
                TextColumn::make('nature_dernier_controle')->label('Nature')->placeholder('—')->badge()->sortable(),
                TextColumn::make('avis')->label('Avis')->placeholder('—')->badge()->sortable(),
                TextColumn::make('date_controle')->label('Date contrôle')->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('created_at')->label('Importé le')->dateTime('d/m/Y H:i')->toggleable()->sortable(),
            ])
            ->defaultSort('date_controle', 'desc')
            ->filters([
                SelectFilter::make('avis')
                    ->label('Avis')
                    ->options(fn () => FicheSpancPyaVersion::query()->distinct()->pluck('avis', 'avis')->filter()),
                SelectFilter::make('nature_dernier_controle')
                    ->label('Nature')
                    ->options(fn () => FicheSpancPyaVersion::query()->distinct()->pluck('nature_dernier_controle', 'nature_dernier_controle')->filter()),
                SelectFilter::make('code_insee_resolu')
                    ->label('Commune (INSEE)')
                    ->options(fn () => FicheSpancPyaVersion::query()->distinct()->pluck('code_insee_resolu', 'code_insee_resolu')->filter()),
                Filter::make('sans_parcelle')
                    ->label('Sans parcelle résolue uniquement')
                    ->toggle()
                    ->query(fn ($query) => $query->where(fn ($q) => $q->whereNull('parcelle_ids')->orWhereJsonLength('parcelle_ids', 0))),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }

    /** @return Collection<int, string> */
    private static function installationIds(FicheSpancPyaVersion $record): Collection
    {
        $ids = $record->parcelle_ids ?: [];

        if ($ids === []) {
            return collect();
        }

        return InstallationParcelleCourante::whereIn('parcelle_id', $ids)->pluck('installation_id')->unique()->values();
    }
}
