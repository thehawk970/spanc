<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Projection jetable : meilleur candidat parcelle par bâtiment. Reconstructible. */
class BatimentParcelleActuel extends Model
{
    protected $table = 'batiment_parcelle_actuel';

    protected $primaryKey = 'batiment_version_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['batiment_version_id', 'parcelle_version_id', 'confiance', 'rapprochement_id', 'maj_le'];

    protected function casts(): array
    {
        return ['maj_le' => 'datetime'];
    }

    public function batimentVersion(): BelongsTo
    {
        return $this->belongsTo(BatimentVersion::class);
    }

    public function parcelleVersion(): BelongsTo
    {
        return $this->belongsTo(ParcelleVersion::class);
    }

    public function rapprochement(): BelongsTo
    {
        return $this->belongsTo(BatimentParcelleRapprochement::class, 'rapprochement_id');
    }
}
