<?php

namespace App\Filament\Resources\FicheSpancPyaVersions\Tables;

use App\Models\FicheSpancPyaVersion;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
}
