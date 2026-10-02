<?php

namespace App\Models;

use App\Observers\InstallationBatimentEvenementObserver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallationBatimentEvenement extends ModeleImmuable
{
    protected static function booted(): void
    {
        static::observe(InstallationBatimentEvenementObserver::class);
    }

    protected $fillable = [
        'installation_id', 'batiment_version_id', 'action', 'source', 'confiance', 'motif', 'auteur_id',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function batimentVersion(): BelongsTo
    {
        return $this->belongsTo(BatimentVersion::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
