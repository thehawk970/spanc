<?php

namespace App\Filament\Resources\Installations\Schemas;

use App\Models\AdresseVersion;
use App\Models\Installation;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InstallationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Installation')
                    ->columns(3)
                    ->components([
                        TextEntry::make('proprietaires')
                            ->label('Propriétaire(s)')
                            ->getStateUsing(function (Installation $record) {
                                $noms = $record->proprietairesCourants()
                                    ->with('proprietaireVersion')
                                    ->get()
                                    ->map(fn ($lien) => trim("{$lien->proprietaireVersion?->nom} {$lien->proprietaireVersion?->prenom}"))
                                    ->filter();

                                return $noms->isEmpty() ? 'Propriétaire inconnu' : $noms->implode(', ');
                            }),
                        TextEntry::make('etatCourant.type')
                            ->label('Type')
                            ->badge(),
                        TextEntry::make('etatCourant.statut')
                            ->label('Statut')
                            ->badge(),
                        TextEntry::make('id')
                            ->label('UUID (référence technique)')
                            ->copyable()
                            ->color('gray'),
                        TextEntry::make('adresse')
                            ->label('Adresse (BAN, via parcelle courante)')
                            ->getStateUsing(function (Installation $record) {
                                $parcelleIds = $record->parcellesCourantes()->pluck('parcelle_id');

                                if ($parcelleIds->isEmpty()) {
                                    return 'Aucune parcelle liée';
                                }

                                $adresses = AdresseVersion::query()
                                    ->where(function ($query) use ($parcelleIds) {
                                        foreach ($parcelleIds as $parcelleId) {
                                            $query->orWhereJsonContains('cad_parcelles', $parcelleId);
                                        }
                                    })
                                    ->get();

                                if ($adresses->isEmpty()) {
                                    return 'Pas d\'adresse BAN trouvée pour cette/ces parcelle(s)';
                                }

                                return $adresses
                                    ->map(fn (AdresseVersion $a) => trim("{$a->numero} {$a->nom_voie}").", {$a->code_postal} {$a->nom_commune}")
                                    ->implode(' | ');
                            })
                            ->columnSpanFull(),
                        TextEntry::make('etatCourant.metadata')
                            ->label('Métadonnées')
                            ->formatStateUsing(fn (?array $state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '—')
                            ->columnSpanFull(),
                        TextEntry::make('creePar.name')
                            ->label('Créée par'),
                        TextEntry::make('created_at')
                            ->label('Créée le')
                            ->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }
}
