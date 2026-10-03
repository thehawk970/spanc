<?php

namespace App\Filament\Resources\CompteurVersions\RelationManagers;

use App\Filament\Resources\Installations\InstallationResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Lecture seule : lier/délier un compteur se fait depuis la fiche
 * installation. La colonne "Bâtiment" distingue les installations quand
 * plusieurs partagent la même parcelle (cf. même logique sur Propriétaire).
 */
class InstallationsRelationManager extends RelationManager
{
    protected static string $relationship = 'installationsCourantes';

    protected static ?string $title = 'Installations';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('installation_id')
            ->modifyQueryUsing(fn ($query) => $query->with(['installation.etatCourant', 'installation.parcellesCourantes', 'installation.batimentsCourants']))
            ->columns([
                TextColumn::make('batiments')
                    ->label('Bâtiment')
                    ->getStateUsing(function ($record) {
                        $ids = $record->installation?->batimentsCourants->pluck('batiment_version_id');

                        return $ids && $ids->isNotEmpty()
                            ? $ids->map(fn ($id) => "Bâtiment #{$id}")->implode(', ')
                            : 'Aucun bâtiment lié';
                    })
                    ->weight('bold'),
                TextColumn::make('installation.etatCourant.type')->label('Type')->badge(),
                TextColumn::make('installation.etatCourant.statut')->label('Statut')->badge(),
                TextColumn::make('parcelles')
                    ->label('Parcelle(s)')
                    ->getStateUsing(function ($record) {
                        $parcelles = $record->installation?->parcellesCourantes->pluck('parcelle_id');

                        return $parcelles && $parcelles->isNotEmpty() ? $parcelles->implode(', ') : '—';
                    }),
                TextColumn::make('maj_le')->label('Lié depuis le')->dateTime('d/m/Y H:i'),
            ])
            ->recordActions([
                Action::make('voir')
                    ->label('Voir')
                    ->url(fn ($record) => InstallationResource::getUrl('view', ['record' => $record->installation_id])),
            ])
            ->headerActions([])
            ->toolbarActions([]);
    }
}
