<?php

namespace App\Filament\Resources\CompteurVersions;

use App\Filament\Resources\CompteurVersions\Pages\ListCompteurVersions;
use App\Filament\Resources\CompteurVersions\Pages\ViewCompteurVersion;
use App\Filament\Resources\CompteurVersions\RelationManagers\InstallationsRelationManager;
use App\Filament\Resources\CompteurVersions\Schemas\CompteurVersionInfolist;
use App\Filament\Resources\CompteurVersions\Tables\CompteurVersionsTable;
use App\Models\CompteurVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Référentiel brut (append-only), alimenté par `sogedo:importer-compteurs`.
 * Lecture seule : le rapprochement abonné/installation se fait ailleurs,
 * plus tard (l'abonné SOGEDO n'est pas forcément le propriétaire actuel).
 */
class CompteurVersionResource extends Resource
{
    protected static ?string $model = CompteurVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $navigationLabel = 'Compteurs d\'eau (SOGEDO)';

    protected static ?string $modelLabel = 'compteur';

    protected static ?string $pluralModelLabel = 'compteurs';

    protected static string|UnitEnum|null $navigationGroup = 'Référentiels importés';

    protected static ?string $recordTitleAttribute = 'numero_compteur';

    public static function infolist(Schema $schema): Schema
    {
        return CompteurVersionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompteurVersionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            InstallationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompteurVersions::route('/'),
            'view' => ViewCompteurVersion::route('/{record}'),
        ];
    }
}
