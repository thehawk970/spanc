<?php

namespace App\Filament\Resources\LogementHorsAssCollVersions;

use App\Filament\Resources\LogementHorsAssCollVersions\Pages\ListLogementHorsAssCollVersions;
use App\Filament\Resources\LogementHorsAssCollVersions\Pages\ViewLogementHorsAssCollVersion;
use App\Filament\Resources\LogementHorsAssCollVersions\RelationManagers\InstallationsRelationManager;
use App\Filament\Resources\LogementHorsAssCollVersions\Schemas\LogementHorsAssCollVersionInfolist;
use App\Filament\Resources\LogementHorsAssCollVersions\Tables\LogementHorsAssCollVersionsTable;
use App\Models\LogementHorsAssCollVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Référentiel brut (append-only), alimenté par
 * `cadastre:importer-logements-hors-ass-coll`. Export fiscal/cadastral
 * (type MAJIC) : lien parcelle exact (idu), propriétaire fiscal, mais
 * aucune colonne de suivi de contrôle SPANC réelle (fichier de travail
 * vierge). Lecture seule.
 */
class LogementHorsAssCollVersionResource extends Resource
{
    protected static ?string $model = LogementHorsAssCollVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static ?string $navigationLabel = 'Logements hors ass. coll. (cadastre)';

    protected static ?string $modelLabel = 'logement';

    protected static ?string $pluralModelLabel = 'logements hors assainissement collectif';

    protected static string|UnitEnum|null $navigationGroup = 'Référentiels importés';

    protected static ?string $recordTitleAttribute = 'parcelle_id';

    public static function infolist(Schema $schema): Schema
    {
        return LogementHorsAssCollVersionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LogementHorsAssCollVersionsTable::configure($table);
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
            'index' => ListLogementHorsAssCollVersions::route('/'),
            'view' => ViewLogementHorsAssCollVersion::route('/{record}'),
        ];
    }
}
