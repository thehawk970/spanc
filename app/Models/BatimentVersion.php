<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BatimentVersion extends ModeleImmuable
{
    protected $fillable = [
        'cle_dedup', 'commune_insee', 'type', 'nom', 'geometry',
        'centroide_lon', 'centroide_lat',
        'bbox_min_lon', 'bbox_max_lon', 'bbox_min_lat', 'bbox_max_lat',
        'import_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'geometry' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    /** Lien courant vers l'installation qui porte ce bâtiment, s'il est référencé. */
    public function installationCourante(): HasOne
    {
        return $this->hasOne(InstallationBatimentCourant::class);
    }

    /** Parcelle déjà rapprochée de ce bâtiment (projection géométrique), s'il y en a une. */
    public function parcelleActuelle(): HasOne
    {
        return $this->hasOne(BatimentParcelleActuel::class);
    }
}
