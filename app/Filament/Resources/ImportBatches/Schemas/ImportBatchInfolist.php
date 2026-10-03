<?php

namespace App\Filament\Resources\ImportBatches\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ImportBatchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Import')
                    ->columns(3)
                    ->components([
                        TextEntry::make('source')->label('Source')->badge(),
                        TextEntry::make('statut')->label('Statut')->badge(),
                        TextEntry::make('nombre_lignes')->label('Lignes')->placeholder('—'),
                        TextEntry::make('fichier_origine')->label('Fichier')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('declenchePar.name')->label('Déclenché par')->placeholder('Système'),
                        TextEntry::make('demarre_le')->label('Démarré le')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('termine_le')->label('Terminé le')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('commentaire')->label('Commentaire')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
