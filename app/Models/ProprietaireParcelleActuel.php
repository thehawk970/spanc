<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Projection jetable. Reconstructible depuis proprietaire_parcelle_rapprochements. */
class ProprietaireParcelleActuel extends Model
{
    protected $table = 'proprietaire_parcelle_actuel';

    public $timestamps = false;

    protected $fillable = ['proprietaire_version_id', 'parcelle_id', 'confiance', 'rapprochement_id', 'maj_le'];

    protected function casts(): array
    {
        return ['maj_le' => 'datetime'];
    }

    public function proprietaireVersion(): BelongsTo
    {
        return $this->belongsTo(ProprietaireVersion::class);
    }

    public function rapprochement(): BelongsTo
    {
        return $this->belongsTo(ProprietaireParcelleRapprochement::class, 'rapprochement_id');
    }
}
