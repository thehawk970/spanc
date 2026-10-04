<?php

namespace App\Filament\Resources\LogementHorsAssCollVersions\Tables;

use App\Models\LogementHorsAssCollVersion;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LogementHorsAssCollVersionsTable
{
    private const COMMUNE_JSON_PATH = "json_extract(proprietes_brutes, '$.\"Commune parcelle\"')";

    private const ADRESSE_CADASTRE_JSON_PATH = "json_extract(proprietes_brutes, '$.\"Adresse parcelle (cadastre)\"')";

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('parcelle_id')->label('Parcelle')->searchable()->sortable(),
                TextColumn::make('numero_proprietaire')->label('N° propriétaire')->searchable()->sortable(),
                TextColumn::make('proprietaire_nom_brut')->label('Propriétaire fiscal (brut)')->placeholder('—')->searchable()->sortable(),
                TextColumn::make('type_habitation')->label('Type')->placeholder('—')->badge()->sortable(),
                TextColumn::make('nombre_locaux')->label('Locaux')->placeholder('—')->sortable(),
                TextColumn::make('commune')
                    ->label('Commune')
                    ->getStateUsing(fn (LogementHorsAssCollVersion $record) => $record->proprietes_brutes['Commune parcelle'] ?? '—')
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw(self::COMMUNE_JSON_PATH.' '.$direction)),
                TextColumn::make('adresse_cadastre')
                    ->label('Adresse (cadastre)')
                    ->getStateUsing(fn (LogementHorsAssCollVersion $record) => $record->proprietes_brutes['Adresse parcelle (cadastre)'] ?? '—')
                    ->wrap()
                    ->sortable(query: fn ($query, string $direction) => $query->orderByRaw(self::ADRESSE_CADASTRE_JSON_PATH.' '.$direction)),
                IconColumn::make('installation_liee')
                    ->label('Installation liée')
                    ->boolean()
                    ->getStateUsing(fn (LogementHorsAssCollVersion $record) => $record->installationsParcelle->isNotEmpty())
                    ->tooltip(fn (LogementHorsAssCollVersion $record) => $record->installationsParcelle->isNotEmpty()
                        ? 'Une installation porte déjà cette parcelle'
                        : "Aucune installation sur cette parcelle (ni la BAN ni ce fichier n'en ont créé une)"),
                TextColumn::make('importBatch.source')->label('Import')->badge()->toggleable()->sortable(),
                TextColumn::make('created_at')->label('Importé le')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('parcelle_id')
            ->filters([
                SelectFilter::make('type_habitation')
                    ->label('Type')
                    ->options(fn () => LogementHorsAssCollVersion::query()->distinct()->pluck('type_habitation', 'type_habitation')->filter()),
                SelectFilter::make('commune')
                    ->label('Commune')
                    ->options(fn () => LogementHorsAssCollVersion::query()
                        ->selectRaw(self::COMMUNE_JSON_PATH.' as commune')
                        ->distinct()
                        ->pluck('commune', 'commune')
                        ->filter())
                    ->query(fn ($query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($query, $value) => $query->whereRaw(self::COMMUNE_JSON_PATH.' = ?', [$value])
                    )),
                SelectFilter::make('import_batch_id')
                    ->label('Import')
                    ->relationship('importBatch', 'source'),
                Filter::make('sans_installation')
                    ->label('Sans installation liée uniquement')
                    ->toggle()
                    ->query(fn ($query) => $query->whereDoesntHave('installationsParcelle')),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
