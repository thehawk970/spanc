<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdresseVersion extends ModeleImmuable
{
    protected $fillable = [
        'id_ban', 'numero', 'repetition', 'nom_voie', 'nom_voie_normalise',
        'code_postal', 'code_insee', 'nom_commune', 'lon', 'lat',
        'cad_parcelles', 'proprietes_brutes', 'import_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'cad_parcelles' => 'array',
            'proprietes_brutes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
