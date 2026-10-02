<?php

namespace App\Models;

use App\Observers\InstallationCompteurEvenementObserver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallationCompteurEvenement extends ModeleImmuable
{
    protected static function booted(): void
    {
        static::observe(InstallationCompteurEvenementObserver::class);
    }

    protected $fillable = [
        'installation_id', 'numero_compteur', 'action', 'source', 'confiance', 'motif', 'auteur_id',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
