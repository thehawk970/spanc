<?php

namespace App\Filament\Resources\ProprietaireVersions\RelationManagers;

use App\Filament\Resources\Installations\InstallationResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Lecture seule : lier/délier un propriétaire se fait depuis la fiche
 * installation (une seule UI pour cette mutation, pas deux qui divergent).
 * L'UUID n'est pas affiché (pas pertinent pour un agent) : type/statut/
 * adresse suffisent à identifier l'installation, avec un lien "Voir".
 *
 * Plusieurs installations peuvent partager la même parcelle (un bâtiment
 * chacune) : sans repère, ça ressemble à un doublon. La colonne "Bâtiment"
 * sert justement à montrer que ce sont des installations distinctes.
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
