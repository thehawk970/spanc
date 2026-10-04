<?php

namespace App\Filament\Resources\CompteurVersions\Tables;

use App\Models\CompteurVersion;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CompteurVersionsTable
{
    private const CODE_REDEVANCE_JSON_PATH = "json_extract(proprietes_brutes, '$.\"Code Redevance 3\"')";

    /** Dernière version de chaque compteur uniquement (voir note sur modifyQueryUsing). */
    private static function versionsActuelles()
    {
        return CompteurVersion::query()->whereIn('id', function ($sub) {
            $sub->selectRaw('MAX(id)')->from('compteur_versions')->groupBy('numero_compteur');
        });
    }

    public static function configure(Table $table): Table
    {
        return $table
            // Un compteur peut avoir ete importe plusieurs fois (correction
            // de donnees) : la liste ne montre que la derniere version de
            // chaque compteur, l'historique complet reste en base.
            ->modifyQueryUsing(fn ($query) => $query->whereIn('id', function ($sub) {
                $sub->selectRaw('MAX(id)')->from('compteur_versions')->groupBy('numero_compteur');
            }))
            ->columns([
                TextColumn::make('numero_compteur')->label('Numéro de compteur')->searchable()->sortable(),
                TextColumn::make('civilite')->label('Civilité')->placeholder('—')->searchable()->sortable(),
                TextColumn::make('abonne_nom_brut')->label('Abonné (brut)')->placeholder('—')->searchable()->sortable(),
                TextColumn::make('adresse_brute')->label('Adresse (brute)')->placeholder('—')->wrap()->searchable()->sortable(),
                TextColumn::make('code_redevance')
                    ->label('Code redevance')
                    ->getStateUsing(fn ($record) => $record->proprietes_brutes['Code Redevance 3'] ?? '—')
                    ->badge()
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw(self::CODE_REDEVANCE_JSON_PATH.' '.$direction)),
                TextColumn::make('importBatch.source')->label('Import')->badge()->toggleable()->sortable(),
                TextColumn::make('created_at')->label('Importé le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('civilite')
                    ->label('Civilité')
                    ->options(fn () => self::versionsActuelles()->distinct()->pluck('civilite', 'civilite')->filter()),
                SelectFilter::make('abonne_nom_brut')
                    ->label('Abonné (brut)')
                    ->searchable()
                    ->options(fn () => self::versionsActuelles()->whereNotNull('abonne_nom_brut')->distinct()->pluck('abonne_nom_brut', 'abonne_nom_brut')),
                SelectFilter::make('adresse_brute')
                    ->label('Adresse (brute)')
                    ->searchable()
                    ->options(fn () => self::versionsActuelles()->whereNotNull('adresse_brute')->distinct()->pluck('adresse_brute', 'adresse_brute')),
                SelectFilter::make('code_redevance')
                    ->label('Code redevance')
                    ->options(fn () => self::versionsActuelles()
                        ->selectRaw(self::CODE_REDEVANCE_JSON_PATH.' as code')
                        ->distinct()
                        ->pluck('code', 'code')
                        ->filter())
                    ->query(fn ($query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($query, $value) => $query->whereRaw(self::CODE_REDEVANCE_JSON_PATH.' = ?', [$value])
                    )),
                SelectFilter::make('import_batch_id')
                    ->label('Import')
                    ->relationship('importBatch', 'source'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
