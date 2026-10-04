<?php

namespace App\Filament\Resources\DispositifSpancVersions;

use App\Filament\Resources\DispositifSpancVersions\Pages\ListDispositifSpancVersions;
use App\Filament\Resources\DispositifSpancVersions\Pages\ViewDispositifSpancVersion;
use App\Filament\Resources\DispositifSpancVersions\Schemas\DispositifSpancVersionInfolist;
use App\Filament\Resources\DispositifSpancVersions\Tables\DispositifSpancVersionsTable;
use App\Models\DispositifSpancVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Référentiel brut (append-only), alimenté par
 * `cadastre:importer-dispositifs-spanc` (export Perigeo). Résumé du dernier
 * contrôle par dossier suivi — l'historique détaillé est dans
 * ControleVersionResource. Lecture seule.
 */
class DispositifSpancVersionResource extends Resource
{
    protected static ?string $model = DispositifSpancVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Dispositifs SPANC (Perigeo)';

    protected static ?string $modelLabel = 'dispositif';

    protected static ?string $pluralModelLabel = 'dispositifs SPANC';

    protected static string|UnitEnum|null $navigationGroup = 'Référentiels importés';

    protected static ?string $recordTitleAttribute = 'reference_dossier';

    public static function infolist(Schema $schema): Schema
    {
        return DispositifSpancVersionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DispositifSpancVersionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDispositifSpancVersions::route('/'),
            'view' => ViewDispositifSpancVersion::route('/{record}'),
        ];
    }
}
