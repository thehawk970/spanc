<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LogementHorsAssCollVersion extends ModeleImmuable
{
    protected $fillable = [
        'parcelle_id', 'numero_proprietaire', 'proprietaire_nom_brut',
        'type_habitation', 'nombre_locaux', 'proprietes_brutes', 'import_batch_id',
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

    /** Installation(s) portant deja cette parcelle (lien exact, pas de FK declaree : parcelle_id est la cle commune). */
    public function installationsParcelle(): HasMany
    {
        return $this->hasMany(InstallationParcelleCourante::class, 'parcelle_id', 'parcelle_id');
    }
}
