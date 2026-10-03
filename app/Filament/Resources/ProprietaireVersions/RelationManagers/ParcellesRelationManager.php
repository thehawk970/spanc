<?php

namespace App\Filament\Resources\ProprietaireVersions\RelationManagers;

use App\Models\AdresseVersion;
use App\Models\ParcelleVersion;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Lecture seule : ce rapprochement est calculé automatiquement par
 * `proprietaires:rapprocher-parcelles` (adresse <-> BAN <-> cad_parcelles),
 * pas édité à la main depuis cette fiche.
 */
class ParcellesRelationManager extends RelationManager
{
    protected static string $relationship = 'parcellesActuelles';

    protected static ?string $title = 'Parcelles';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('parcelle_id')
            ->columns([
                TextColumn::make('parcelle_id')->label('Parcelle'),
                TextColumn::make('commune')
                    ->label('Commune / section')
                    ->getStateUsing(function ($record) {
                        $pv = ParcelleVersion::where('parcelle_id', $record->parcelle_id)->latest('id')->first();

                        return $pv ? "{$pv->commune_insee} / {$pv->section}{$pv->numero}" : '—';
                    }),
                TextColumn::make('surface')
                    ->label('Surface')
                    ->getStateUsing(function ($record) {
                        $pv = ParcelleVersion::where('parcelle_id', $record->parcelle_id)->latest('id')->first();

                        return $pv?->surface_m2 ? number_format($pv->surface_m2, 0, ',', ' ').' m²' : '—';
                    }),
                TextColumn::make('adresse_ban')
                    ->label('Adresse (BAN)')
                    ->getStateUsing(function ($record) {
                        $adresses = AdresseVersion::whereJsonContains('cad_parcelles', $record->parcelle_id)->get();

                        if ($adresses->isEmpty()) {
                            return '—';
                        }

                        return $adresses
                            ->map(fn (AdresseVersion $a) => trim("{$a->numero} {$a->nom_voie}").", {$a->code_postal} {$a->nom_commune}")
                            ->implode(' | ');
                    })
                    ->wrap(),
                TextColumn::make('confiance')->label('Confiance'),
                TextColumn::make('maj_le')->label('Rapproché le')->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
