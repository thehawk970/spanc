<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plomberie opérationnelle, volontairement mutable (le statut avance
 * pendant l'exécution du job) — pas soumise à l'immuabilité.
 */
class ImportBatch extends Model
{
    protected $fillable = [
        'source', 'fichier_origine', 'declenche_par_id', 'statut',
        'nombre_lignes', 'commentaire', 'demarre_le', 'termine_le',
    ];

    protected function casts(): array
    {
        return [
            'demarre_le' => 'datetime',
            'termine_le' => 'datetime',
        ];
    }

    public function declenchePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declenche_par_id');
    }
}
