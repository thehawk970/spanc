<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Projection jetable des liens compteur actifs. Reconstructible. */
class InstallationCompteurCourant extends Model
{
    protected $table = 'installation_compteur_courant';

    public $timestamps = false;

    protected $fillable = ['installation_id', 'numero_compteur', 'confiance', 'evenement_id', 'maj_le'];

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
        return $this->belongsTo(InstallationCompteurEvenement::class, 'evenement_id');
    }
}
