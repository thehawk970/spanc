<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * L'ancre stable : un UUID qui ne porte quasiment aucun attribut mutable.
 * Le type, le statut et les métadonnées vivent dans les états (append-only).
 */
class Installation extends ModeleImmuable
{
    use HasUuids;

    protected $table = 'installations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['cree_par_id'];

    public function creePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par_id');
    }

    public function etats(): HasMany
    {
        return $this->hasMany(InstallationEtat::class);
    }

    public function etatCourant(): HasOne
    {
        return $this->hasOne(InstallationEtatCourant::class);
    }

    public function parcelleEvenements(): HasMany
    {
        return $this->hasMany(InstallationParcelleEvenement::class);
    }

    public function parcellesCourantes(): HasMany
    {
        return $this->hasMany(InstallationParcelleCourante::class);
    }

    public function batimentEvenements(): HasMany
    {
        return $this->hasMany(InstallationBatimentEvenement::class);
    }

    public function batimentsCourants(): HasMany
    {
        return $this->hasMany(InstallationBatimentCourant::class);
    }

    public function compteurEvenements(): HasMany
    {
        return $this->hasMany(InstallationCompteurEvenement::class);
    }

    public function compteursCourants(): HasMany
    {
        return $this->hasMany(InstallationCompteurCourant::class);
    }

    public function proprietaireEvenements(): HasMany
    {
        return $this->hasMany(InstallationProprietaireEvenement::class);
    }

    public function proprietairesCourants(): HasMany
    {
        return $this->hasMany(InstallationProprietaireCourante::class);
    }

    public function rapports(): HasMany
    {
        return $this->hasMany(Rapport::class);
    }

    public function rapportCourant(): HasOne
    {
        return $this->hasOne(InstallationRapportCourant::class);
    }

    private array|false|null $communeActuelleCache = null;

    /**
     * Commune de la première parcelle courante (le cadastre couvre 100% des
     * parcelles, contrairement à la BAN qui ne couvre qu'une partie des
     * adresses) : source la plus fiable pour situer une installation.
     * Mise en cache sur l'instance (appelée deux fois par ligne de tableau :
     * colonne code INSEE + colonne commune).
     *
     * @return array{code_insee: string, nom: ?string}|null
     */
    public function communeActuelle(): ?array
    {
        if ($this->communeActuelleCache !== null) {
            return $this->communeActuelleCache ?: null;
        }

        $parcelleId = $this->relationLoaded('parcellesCourantes')
            ? $this->parcellesCourantes->first()?->parcelle_id
            : $this->parcellesCourantes()->value('parcelle_id');

        if (! $parcelleId) {
            $this->communeActuelleCache = false;

            return null;
        }

        $codeInsee = ParcelleVersion::where('parcelle_id', $parcelleId)->value('commune_insee');

        if (! $codeInsee) {
            $this->communeActuelleCache = false;

            return null;
        }

        return $this->communeActuelleCache = [
            'code_insee' => $codeInsee,
            'nom' => AdresseVersion::where('code_insee', $codeInsee)->value('nom_commune'),
        ];
    }
}
