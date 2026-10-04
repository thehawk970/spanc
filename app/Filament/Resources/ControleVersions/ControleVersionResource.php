<?php

namespace App\Filament\Resources\ControleVersions;

use App\Filament\Resources\ControleVersions\Pages\ListControleVersions;
use App\Filament\Resources\ControleVersions\Pages\ViewControleVersion;
use App\Filament\Resources\ControleVersions\Schemas\ControleVersionInfolist;
use App\Filament\Resources\ControleVersions\Tables\ControleVersionsTable;
use App\Models\ControleVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Référentiel brut (append-only), alimenté par
 * `cadastre:importer-controles` (exports Perigeo "BE" et "DPV" fusionnés).
 * Historique des évènements de contrôle, à l'inverse de
 * DispositifSpancVersionResource qui ne garde que le dernier. Lecture
 * seule.
 */
class ControleVersionResource extends Resource
{
    protected static ?string $model = ControleVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMagnifyingGlass;

    protected static ?string $navigationLabel = 'Contrôles (Perigeo)';

    protected static ?string $modelLabel = 'contrôle';

    protected static ?string $pluralModelLabel = 'contrôles';

    protected static string|UnitEnum|null $navigationGroup = 'Référentiels importés';

    protected static ?string $recordTitleAttribute = 'reference_controle';

    public static function infolist(Schema $schema): Schema
    {
        return ControleVersionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ControleVersionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListControleVersions::route('/'),
            'view' => ViewControleVersion::route('/{record}'),
        ];
    }
}
