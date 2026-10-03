<?php

namespace App\Filament\Resources\Installations\RelationManagers;

use App\Models\ImportBatch;
use App\Models\InstallationProprietaireEvenement;
use App\Models\ProprietaireVersion;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * "Lier" cherche d'abord dans le référentiel déjà importé (ex: le CSV
 * propriétaires, 1976 lignes) par nom/prénom, avec l'adresse source en
 * repère pour départager les homonymes. Si la personne n'existe pas encore
 * dans le référentiel, le formulaire de création inline du Select ajoute une
 * nouvelle version (saisie manuelle, rattachée à un import_batch dédié).
 */
class ProprietairesCourantsRelationManager extends RelationManager
{
    protected static string $relationship = 'proprietairesCourants';

    protected static ?string $title = 'Propriétaires';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('proprietaireVersion.nom')->label('Nom'),
                TextColumn::make('proprietaireVersion.prenom')->label('Prénom')->placeholder('—'),
                TextColumn::make('proprietaireVersion.contact')->label('Contact')->placeholder('—'),
                TextColumn::make('maj_le')->label('Depuis le')->dateTime('d/m/Y H:i'),
            ])
            ->headerActions([
                Action::make('lier')
                    ->label('Lier un propriétaire')
                    ->icon('heroicon-o-link')
                    ->schema([
                        Select::make('proprietaire_version_id')
                            ->label('Propriétaire')
                            ->helperText('Recherche dans le référentiel déjà importé (nom ou prénom).')
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => ProprietaireVersion::query()
                                ->where('nom', 'like', "%{$search}%")
                                ->orWhere('prenom', 'like', "%{$search}%")
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn (ProprietaireVersion $p) => [$p->id => self::libelle($p)]))
                            ->getOptionLabelUsing(fn ($value) => self::libelle(ProprietaireVersion::find($value)))
                            ->createOptionForm([
                                TextInput::make('nom')->label('Nom')->required()->maxLength(255),
                                TextInput::make('prenom')->label('Prénom')->maxLength(255),
                                TextInput::make('contact')->label('Contact (téléphone/email)')->maxLength(255),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $batch = ImportBatch::create([
                                    'source' => 'manuel',
                                    'statut' => 'termine',
                                    'nombre_lignes' => 1,
                                    'declenche_par_id' => auth()->id(),
                                    'demarre_le' => now(),
                                    'termine_le' => now(),
                                    'commentaire' => 'Saisie manuelle via Filament (introuvable dans le référentiel importé)',
                                ]);

                                return ProprietaireVersion::create([
                                    'nom' => $data['nom'],
                                    'prenom' => $data['prenom'] ?? null,
                                    'contact' => $data['contact'] ?? null,
                                    'import_batch_id' => $batch->id,
                                ])->id;
                            })
                            ->required(),
                        TextInput::make('motif')
                            ->label('Motif')
                            ->maxLength(255),
                    ])
                    ->action(function (array $data) {
                        InstallationProprietaireEvenement::create([
                            'installation_id' => $this->getOwnerRecord()->id,
                            'proprietaire_version_id' => $data['proprietaire_version_id'],
                            'action' => 'lier',
                            'source' => 'manuel',
                            'motif' => $data['motif'] ?? null,
                            'auteur_id' => auth()->id(),
                        ]);
                    }),
            ])
            ->recordActions([
                Action::make('delier')
                    ->label('Délier')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->schema([
                        TextInput::make('motif')
                            ->label('Motif (ex : vente, erreur de saisie)')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->action(function (array $data, $record) {
                        InstallationProprietaireEvenement::create([
                            'installation_id' => $this->getOwnerRecord()->id,
                            'proprietaire_version_id' => $record->proprietaire_version_id,
                            'action' => 'delier',
                            'source' => 'manuel',
                            'motif' => $data['motif'],
                            'auteur_id' => auth()->id(),
                        ]);
                    }),
            ])
            ->toolbarActions([]);
    }

    private static function libelle(?ProprietaireVersion $proprietaire): string
    {
        if (! $proprietaire) {
            return '';
        }

        $nomComplet = trim("{$proprietaire->nom} {$proprietaire->prenom}");

        $contexte = array_filter([
            $proprietaire->proprietes_brutes['Points Adresses - base locale'] ?? null,
            $proprietaire->proprietes_brutes['commune pro'] ?? null,
        ]);

        return $contexte === [] ? $nomComplet : "{$nomComplet} — ".implode(', ', $contexte);
    }
}
