<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Projection jetable. Reconstructible depuis compteur_parcelle_rapprochements. */
class CompteurParcelleActuel extends Model
{
    protected $table = 'compteur_parcelle_actuel';

    public $timestamps = false;

    protected $fillable = ['numero_compteur', 'parcelle_id', 'confiance', 'rapprochement_id', 'maj_le'];

    protected function casts(): array
    {
        return ['maj_le' => 'datetime'];
    }

    public function rapprochement(): BelongsTo
    {
        return $this->belongsTo(CompteurParcelleRapprochement::class, 'rapprochement_id');
    }
}
