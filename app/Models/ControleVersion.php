<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ControleVersion extends ModeleImmuable
{
    protected $fillable = [
        'reference_controle', 'type_source', 'reference_dossier_liee',
        'commune', 'cadre_ou_type', 'type_filiere', 'lien_dossier',
        'date_visite', 'technicien', 'etat_controle', 'avis',
        'proprietaire_brut', 'usager_brut', 'adresse_parcelle_brute', 'adresse_proprietaire_brute',
        'proprietes_brutes', 'import_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'proprietes_brutes' => 'array',
            'date_visite' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    /** Dossier dispositif lie, s'il a ete retrouve a l'import (voir ImporterControlesCommand). */
    public function dispositif(): BelongsTo
    {
        return $this->belongsTo(DispositifSpancVersion::class, 'reference_dossier_liee', 'reference_dossier');
    }
}
