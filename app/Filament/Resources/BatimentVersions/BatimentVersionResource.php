<?php

namespace App\Filament\Resources\BatimentVersions;

use App\Filament\Resources\BatimentVersions\Pages\ListBatimentVersions;
use App\Filament\Resources\BatimentVersions\Pages\ViewBatimentVersion;
use App\Filament\Resources\BatimentVersions\Schemas\BatimentVersionInfolist;
use App\Filament\Resources\BatimentVersions\Tables\BatimentVersionsTable;
use App\Models\BatimentVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Référentiel brut (append-only), alimenté uniquement par
 * `cadastre:importer-batiments`. Lecture seule.
 */
class BatimentVersionResource extends Resource
{
    protected static ?string $model = BatimentVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Bâtiments (cadastre)';

    protected static ?string $modelLabel = 'bâtiment';

    protected static ?string $pluralModelLabel = 'bâtiments';

    protected static string|UnitEnum|null $navigationGroup = 'Référentiels importés';

    protected static ?string $recordTitleAttribute = 'id';

    public static function infolist(Schema $schema): Schema
    {
        return BatimentVersionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BatimentVersionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBatimentVersions::route('/'),
            'view' => ViewBatimentVersion::route('/{record}'),
        ];
    }
}
