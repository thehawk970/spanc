<?php

namespace App\Filament\Resources\ControleVersions\Tables;

use App\Models\ControleVersion;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ControleVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference_controle')->label('Référence')->placeholder('—')->searchable()->sortable(),
                TextColumn::make('type_source')->label('Source')->badge()->sortable(),
                IconColumn::make('dossier_trouve')
                    ->label('Dossier relié')
                    ->boolean()
                    ->getStateUsing(fn (ControleVersion $record) => $record->dispositif()->exists())
                    ->tooltip(fn (ControleVersion $record) => $record->dispositif()->exists()
                        ? 'Relié à un dispositif SPANC connu'
                        : 'Pas de dispositif correspondant (reference_dossier_liee introuvable ou absente)'),
                TextColumn::make('commune')->label('Commune')->placeholder('—')->searchable()->sortable(),
                TextColumn::make('cadre_ou_type')->label('Cadre / Type')->placeholder('—')->wrap()->sortable(),
                TextColumn::make('date_visite')->label('Date de visite')->date('d/m/Y')->placeholder('—')->sortable(),
                TextColumn::make('technicien')->label('Technicien')->placeholder('—')->searchable()->sortable(),
                TextColumn::make('etat_controle')->label('État')->placeholder('—')->badge()->sortable(),
                TextColumn::make('avis')->label('Avis')->placeholder('—')->badge()->wrap()->sortable(),
                TextColumn::make('usager_brut')->label('Usager')->placeholder('—')->searchable()->sortable(),
                TextColumn::make('created_at')->label('Importé le')->dateTime('d/m/Y H:i')->toggleable()->sortable(),
            ])
            ->defaultSort('date_visite', 'desc')
            ->filters([
                SelectFilter::make('type_source')
                    ->label('Source')
                    ->options(['be' => 'BE (bonne exécution)', 'dpv' => 'DPV (diagnostic/vente)']),
                SelectFilter::make('etat_controle')
                    ->label('État')
                    ->options(fn () => ControleVersion::query()->distinct()->pluck('etat_controle', 'etat_controle')->filter()),
                SelectFilter::make('avis')
                    ->label('Avis')
                    ->options(fn () => ControleVersion::query()->distinct()->pluck('avis', 'avis')->filter()),
                SelectFilter::make('commune')
                    ->label('Commune')
                    ->options(fn () => ControleVersion::query()->distinct()->pluck('commune', 'commune')->filter()),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
