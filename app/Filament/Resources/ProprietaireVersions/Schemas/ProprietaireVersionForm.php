<?php

namespace App\Filament\Resources\ProprietaireVersions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Sert uniquement à la création manuelle (pas d'import en masse depuis
 * cette page). Voir CreateProprietaireVersion::handleRecordCreation().
 */
class ProprietaireVersionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nom')->label('Nom')->required()->maxLength(255),
                TextInput::make('prenom')->label('Prénom')->maxLength(255),
                TextInput::make('contact')->label('Contact (téléphone/email)')->maxLength(255),
            ]);
    }
}
