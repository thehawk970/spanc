<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Projection jetable des liens bâtiment actifs. Reconstructible. */
class InstallationBatimentCourant extends Model
{
    protected $table = 'installation_batiment_courant';

    public $timestamps = false;

    protected $fillable = ['installation_id', 'batiment_version_id', 'confiance', 'evenement_id', 'maj_le'];

    protected function casts(): array
    {
        return ['maj_le' => 'datetime'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function batimentVersion(): BelongsTo
    {
        return $this->belongsTo(BatimentVersion::class);
    }

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(InstallationBatimentEvenement::class, 'evenement_id');
    }
}
