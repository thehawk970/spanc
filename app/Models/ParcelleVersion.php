<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcelleVersion extends ModeleImmuable
{
    protected $fillable = [
        'parcelle_id', 'commune_insee', 'section', 'numero', 'geometry',
        'surface_m2', 'proprietes_brutes', 'import_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'geometry' => 'array',
            'proprietes_brutes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
