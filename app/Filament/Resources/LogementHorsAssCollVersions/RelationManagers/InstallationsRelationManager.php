<?php

namespace App\Filament\Resources\LogementHorsAssCollVersions\RelationManagers;

use App\Filament\Resources\Installations\InstallationResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Lien par parcelle (pas une vraie FK) : montre la ou les installations qui
 * portent deja la parcelle de ce logement. Vide = aucune installation sur
 * cette parcelle, ni via la BAN ni via ce fichier — un vrai trou de
 * couverture (cf. filtre "Sans installation liee" sur la liste).
 */
class InstallationsRelationManager extends RelationManager
{
    protected static string $relationship = 'installationsParcelle';

    protected static ?string $title = 'Installation(s) sur cette parcelle';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('installation_id')
            ->modifyQueryUsing(fn ($query) => $query->with(['installation.etatCourant', 'installation.proprietairesCourants.proprietaireVersion']))
            ->columns([
                TextColumn::make('installation_id')
                    ->label('Installation')
                    ->formatStateUsing(fn (string $state) => mb_substr($state, 0, 8).'…'),
                TextColumn::make('installation.etatCourant.type')->label('Type')->badge()->sortable(),
                TextColumn::make('installation.etatCourant.statut')->label('Statut')->badge()->sortable(),
                TextColumn::make('proprietaires')
                    ->label('Propriétaire(s) lié(s)')
                    ->getStateUsing(function ($record) {
                        $noms = $record->installation?->proprietairesCourants
                            ->map(fn ($lien) => trim("{$lien->proprietaireVersion?->nom} {$lien->proprietaireVersion?->prenom}"))
                            ->filter();

                        return $noms && $noms->isNotEmpty() ? $noms->implode(', ') : '—';
                    }),
                TextColumn::make('maj_le')->label('Lié depuis le')->dateTime('d/m/Y H:i')->sortable(),
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
