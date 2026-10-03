<?php

namespace App\Filament\Resources\Installations;

use App\Filament\Resources\Installations\Pages\CreateInstallation;
use App\Filament\Resources\Installations\Pages\ListInstallations;
use App\Filament\Resources\Installations\Pages\ViewInstallation;
use App\Filament\Resources\Installations\RelationManagers\BatimentsCourantsRelationManager;
use App\Filament\Resources\Installations\RelationManagers\CompteursCourantsRelationManager;
use App\Filament\Resources\Installations\RelationManagers\EtatsRelationManager;
use App\Filament\Resources\Installations\RelationManagers\ParcellesCourantesRelationManager;
use App\Filament\Resources\Installations\RelationManagers\ProprietairesCourantsRelationManager;
use App\Filament\Resources\Installations\RelationManagers\RapportsRelationManager;
use App\Filament\Resources\Installations\Schemas\InstallationForm;
use App\Filament\Resources\Installations\Schemas\InstallationInfolist;
use App\Filament\Resources\Installations\Tables\InstallationsTable;
use App\Models\Installation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Pas de page Edit : Installation n'a (presque) aucun attribut mutable.
 * Tout changement passe par un nouvel évènement (cf. les relation managers),
 * jamais par une modification en place.
 */
class InstallationResource extends Resource
{
    protected static ?string $model = Installation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Installations';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return InstallationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InstallationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstallationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            EtatsRelationManager::class,
            ParcellesCourantesRelationManager::class,
            BatimentsCourantsRelationManager::class,
            CompteursCourantsRelationManager::class,
            ProprietairesCourantsRelationManager::class,
            RapportsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstallations::route('/'),
            'create' => CreateInstallation::route('/create'),
            'view' => ViewInstallation::route('/{record}'),
        ];
    }
}
