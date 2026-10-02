<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Projection jetable : dernier état connu par installation. Entièrement
 * reconstructible depuis installation_etats (artisan projections:rebuild).
 * Ce n'est pas une source de vérité, donc ni immuable ni append-only.
 */
class InstallationEtatCourant extends Model
{
    protected $table = 'installation_etat_courant';

    protected $primaryKey = 'installation_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['installation_id', 'type', 'statut', 'metadata', 'evenement_id', 'maj_le'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'maj_le' => 'datetime',
        ];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(InstallationEtat::class, 'evenement_id');
    }
}
