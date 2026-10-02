<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
