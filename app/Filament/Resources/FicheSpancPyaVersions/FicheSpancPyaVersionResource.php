<?php

namespace App\Filament\Resources\FicheSpancPyaVersions;

use App\Filament\Resources\FicheSpancPyaVersions\Pages\ListFicheSpancPyaVersions;
use App\Filament\Resources\FicheSpancPyaVersions\Pages\ViewFicheSpancPyaVersion;
use App\Filament\Resources\FicheSpancPyaVersions\Schemas\FicheSpancPyaVersionInfolist;
use App\Filament\Resources\FicheSpancPyaVersions\Tables\FicheSpancPyaVersionsTable;
use App\Models\FicheSpancPyaVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Référentiel brut (append-only), alimenté par
 * `cadastre:importer-fiches-spanc-pya` (export XLSX d'un deuxième logiciel
 * de diagnostic, distinct de Perigeo). Lié à la parcelle uniquement
 * (`parcelle_ids`, jamais au propriétaire — voir la commande). Lecture
 * seule.
 */
class FicheSpancPyaVersionResource extends Resource
{
    protected static ?string $model = FicheSpancPyaVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Fiches SPANC (2e logiciel)';

    protected static ?string $modelLabel = 'fiche';

    protected static ?string $pluralModelLabel = 'fiches SPANC (2e logiciel)';

    protected static string|UnitEnum|null $navigationGroup = 'Référentiels importés';

    protected static ?string $recordTitleAttribute = 'id_source';

    public static function infolist(Schema $schema): Schema
    {
        return FicheSpancPyaVersionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FicheSpancPyaVersionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFicheSpancPyaVersions::route('/'),
            'view' => ViewFicheSpancPyaVersion::route('/{record}'),
        ];
    }
}
