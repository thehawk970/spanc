<?php

namespace App\Filament\Resources\ImportBatches;

use App\Filament\Resources\ImportBatches\Pages\ListImportBatches;
use App\Filament\Resources\ImportBatches\Pages\ViewImportBatch;
use App\Filament\Resources\ImportBatches\Schemas\ImportBatchInfolist;
use App\Filament\Resources\ImportBatches\Tables\ImportBatchesTable;
use App\Models\ImportBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Journal de tous les imports (cadastre, BAN, propriétaires, rapprochements
 * géométriques/adresse). Mutable en base (le statut avance pendant le job),
 * mais lecture seule depuis l'interface : ce n'est pas à éditer à la main.
 */
class ImportBatchResource extends Resource
{
    protected static ?string $model = ImportBatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = "Journal des imports";

    protected static ?string $modelLabel = 'import';

    protected static ?string $pluralModelLabel = 'imports';

    protected static string|UnitEnum|null $navigationGroup = 'Référentiels importés';

    protected static ?string $recordTitleAttribute = 'source';

    public static function infolist(Schema $schema): Schema
    {
        return ImportBatchInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ImportBatchesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportBatches::route('/'),
            'view' => ViewImportBatch::route('/{record}'),
        ];
    }
}
