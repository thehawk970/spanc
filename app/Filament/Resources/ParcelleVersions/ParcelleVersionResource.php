<?php

namespace App\Filament\Resources\ParcelleVersions;

use App\Filament\Resources\ParcelleVersions\Pages\ListParcelleVersions;
use App\Filament\Resources\ParcelleVersions\Pages\ViewParcelleVersion;
use App\Filament\Resources\ParcelleVersions\Schemas\ParcelleVersionInfolist;
use App\Filament\Resources\ParcelleVersions\Tables\ParcelleVersionsTable;
use App\Models\ParcelleVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Référentiel brut (append-only), alimenté uniquement par
 * `cadastre:importer-parcelles`. Lecture seule : pas de création ni
 * d'édition depuis l'interface.
 */
class ParcelleVersionResource extends Resource
{
    protected static ?string $model = ParcelleVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $navigationLabel = 'Parcelles (cadastre)';

    protected static ?string $modelLabel = 'parcelle';

    protected static ?string $pluralModelLabel = 'parcelles';

    protected static string|UnitEnum|null $navigationGroup = 'Référentiels importés';

    protected static ?string $recordTitleAttribute = 'parcelle_id';

    public static function infolist(Schema $schema): Schema
    {
        return ParcelleVersionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ParcelleVersionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParcelleVersions::route('/'),
            'view' => ViewParcelleVersion::route('/{record}'),
        ];
    }
}
