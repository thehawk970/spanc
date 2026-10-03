<?php

namespace App\Filament\Resources\AdresseVersions;

use App\Filament\Resources\AdresseVersions\Pages\ListAdresseVersions;
use App\Filament\Resources\AdresseVersions\Pages\ViewAdresseVersion;
use App\Filament\Resources\AdresseVersions\Schemas\AdresseVersionInfolist;
use App\Filament\Resources\AdresseVersions\Tables\AdresseVersionsTable;
use App\Models\AdresseVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Référentiel brut (append-only), alimenté uniquement par
 * `ban:importer-adresses`. Lecture seule.
 */
class AdresseVersionResource extends Resource
{
    protected static ?string $model = AdresseVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Adresses (BAN)';

    protected static ?string $modelLabel = 'adresse';

    protected static ?string $pluralModelLabel = 'adresses';

    protected static string|UnitEnum|null $navigationGroup = 'Référentiels importés';

    protected static ?string $recordTitleAttribute = 'nom_voie';

    public static function infolist(Schema $schema): Schema
    {
        return AdresseVersionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdresseVersionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdresseVersions::route('/'),
            'view' => ViewAdresseVersion::route('/{record}'),
        ];
    }
}
