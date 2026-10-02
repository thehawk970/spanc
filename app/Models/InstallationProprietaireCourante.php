<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Projection jetable des propriétaires actuellement liés. Reconstructible. */
class InstallationProprietaireCourante extends Model
{
    protected $table = 'installation_proprietaire_courante';

    public $timestamps = false;

    protected $fillable = ['installation_id', 'proprietaire_version_id', 'evenement_id', 'maj_le'];

    protected function casts(): array
    {
        return ['maj_le' => 'datetime'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function proprietaireVersion(): BelongsTo
    {
        return $this->belongsTo(ProprietaireVersion::class);
    }

    public function evenement(): BelongsTo
    {
        return $this->belongsTo(InstallationProprietaireEvenement::class, 'evenement_id');
    }
}
