<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompteurVersion extends ModeleImmuable
{
    protected $fillable = [
        'numero_compteur', 'adresse_brute', 'abonne_nom_brut', 'civilite', 'proprietes_brutes', 'import_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'proprietes_brutes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    /**
     * Clé métier (numero_compteur), pas l'id de version : un même compteur
     * peut avoir plusieurs versions importées, toutes partagent les mêmes
     * liens installation.
     */
    public function installationsCourantes(): HasMany
    {
        return $this->hasMany(InstallationCompteurCourant::class, 'numero_compteur', 'numero_compteur');
    }
}
