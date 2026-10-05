<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Projection jetable : rapport le plus récent par date_controle (pas par
 * ordre d'insertion) pour chaque installation. Entièrement reconstructible
 * depuis rapports (artisan projections:rebuild). Ce n'est pas une source
 * de vérité, donc ni immuable ni append-only.
 */
class InstallationRapportCourant extends Model
{
    protected $table = 'installation_rapport_courant';

    protected $primaryKey = 'installation_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['installation_id', 'rapport_id', 'type_controle', 'date_controle', 'conclusion', 'maj_le'];

    protected function casts(): array
    {
        return [
            'date_controle' => 'date',
            'maj_le' => 'datetime',
        ];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function rapport(): BelongsTo
    {
        return $this->belongsTo(Rapport::class);
    }
}
