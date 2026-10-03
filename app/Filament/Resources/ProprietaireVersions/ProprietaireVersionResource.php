<?php

namespace App\Filament\Resources\ProprietaireVersions;

use App\Filament\Resources\ProprietaireVersions\Pages\CreateProprietaireVersion;
use App\Filament\Resources\ProprietaireVersions\Pages\ListProprietaireVersions;
use App\Filament\Resources\ProprietaireVersions\Pages\ViewProprietaireVersion;
use App\Filament\Resources\ProprietaireVersions\RelationManagers\InstallationsRelationManager;
use App\Filament\Resources\ProprietaireVersions\RelationManagers\ParcellesRelationManager;
use App\Filament\Resources\ProprietaireVersions\Schemas\ProprietaireVersionForm;
use App\Filament\Resources\ProprietaireVersions\Schemas\ProprietaireVersionInfolist;
use App\Filament\Resources\ProprietaireVersions\Tables\ProprietaireVersionsTable;
use App\Models\ProprietaireVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Référentiel versionné (append-only) : pas de page Edit, un "propriétaire"
 * existant ne se corrige jamais en place — une nouvelle version se crée
 * (import ou saisie manuelle) et les liens se refont via les installations.
 */
class ProprietaireVersionResource extends Resource
{
    protected static ?string $model = ProprietaireVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Propriétaires';

    protected static string|UnitEnum|null $navigationGroup = 'Référentiels importés';

    protected static ?string $modelLabel = 'propriétaire';

    protected static ?string $pluralModelLabel = 'propriétaires';

    protected static ?string $recordTitleAttribute = 'nom';

    public static function form(Schema $schema): Schema
    {
        return ProprietaireVersionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProprietaireVersionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProprietaireVersionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ParcellesRelationManager::class,
            InstallationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProprietaireVersions::route('/'),
            'create' => CreateProprietaireVersion::route('/create'),
            'view' => ViewProprietaireVersion::route('/{record}'),
        ];
    }
}
