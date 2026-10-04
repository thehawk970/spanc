<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispositifSpancVersion extends ModeleImmuable
{
    protected $fillable = [
        'reference_dossier', 'code_insee', 'section', 'numero',
        'date_derniere_visite', 'technicien', 'nature_dernier_controle', 'avis_dernier_controle', 'type_filiere',
        'nom_cadastre', 'prenom_cadastre', 'nom_spanc', 'prenom_spanc',
        'proprietes_brutes', 'import_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'proprietes_brutes' => 'array',
            'date_derniere_visite' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
