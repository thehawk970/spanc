<?php

namespace App\Filament\Resources\DispositifSpancVersions\Tables;

use App\Models\DispositifSpancVersion;
use App\Models\ParcelleVersion;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DispositifSpancVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_dossier')->label('Dossier')->searchable()->sortable(),
                TextColumn::make('parcelle')
                    ->label('Parcelle')
                    ->getStateUsing(fn (DispositifSpancVersion $record) => trim(($record->section ?? '').($record->numero ?? '')) ?: '—'),
                IconColumn::make('parcelle_trouvee')
                    ->label('Parcelle connue')
                    ->boolean()
                    ->getStateUsing(fn (DispositifSpancVersion $record) => $record->section && $record->numero
                        && ParcelleVersion::where('commune_insee', $record->code_insee)->where('section', $record->section)->where('numero', $record->numero)->exists()),
                TextColumn::make('nom_cadastre')->label('Propriétaire (cadastre)')->placeholder('—')->searchable()->sortable(),
                TextColumn::make('nom_spanc')->label('Propriétaire (SPANC)')->placeholder('—')->searchable()->sortable(),
                TextColumn::make('type_filiere')->label('Filière')->placeholder('—')->badge()->sortable(),
                TextColumn::make('date_derniere_visite')->label('Dernière visite')->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('nature_dernier_controle')->label('Nature')->placeholder('—')->badge()->sortable(),
                TextColumn::make('avis_dernier_controle')->label('Avis')->placeholder('—')->badge()->sortable(),
                TextColumn::make('created_at')->label('Importé le')->dateTime('d/m/Y H:i')->toggleable()->sortable(),
            ])
            ->defaultSort('date_derniere_visite', 'desc')
            ->filters([
                SelectFilter::make('avis_dernier_controle')
                    ->label('Avis')
                    ->options(fn () => DispositifSpancVersion::query()->distinct()->pluck('avis_dernier_controle', 'avis_dernier_controle')->filter()),
                SelectFilter::make('nature_dernier_controle')
                    ->label('Nature')
                    ->options(fn () => DispositifSpancVersion::query()->distinct()->pluck('nature_dernier_controle', 'nature_dernier_controle')->filter()),
                SelectFilter::make('type_filiere')
                    ->label('Filière')
                    ->options(fn () => DispositifSpancVersion::query()->distinct()->pluck('type_filiere', 'type_filiere')->filter()),
                SelectFilter::make('code_insee')
                    ->label('Commune (INSEE)')
                    ->options(fn () => DispositifSpancVersion::query()->distinct()->pluck('code_insee', 'code_insee')->filter()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
