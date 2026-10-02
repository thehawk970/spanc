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
}
