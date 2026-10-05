<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FicheSpancPyaVersion extends ModeleImmuable
{
    protected $fillable = [
        'id_source', 'date_controle', 'nature_dernier_controle', 'avis', 'conformite',
        'ville_terrain_brute', 'code_insee_resolu', 'section_numero_brute', 'parcelle_ids',
        'proprietes_brutes', 'import_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'parcelle_ids' => 'array',
            'proprietes_brutes' => 'array',
            'date_controle' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
