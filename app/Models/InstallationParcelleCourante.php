<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Projection jetable des liens parcelle actifs. Reconstructible. */
class InstallationParcelleCourante extends Model
{
    protected $table = 'installation_parcelle_courante';

    public $timestamps = false;

    protected $fillable = ['installation_id', 'parcelle_id', 'confiance', 'evenement_id', 'maj_le'];

    protected function casts(): array
    {
        return ['maj_le' => 'datetime'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(InstallationParcelleEvenement::class, 'evenement_id');
    }
}
