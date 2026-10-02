<?php

namespace App\Models;

use App\Observers\InstallationProprietaireEvenementObserver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallationProprietaireEvenement extends ModeleImmuable
{
    protected static function booted(): void
    {
        static::observe(InstallationProprietaireEvenementObserver::class);
    }

    protected $fillable = [
        'installation_id', 'proprietaire_version_id', 'action', 'source', 'confiance', 'motif', 'auteur_id',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function proprietaireVersion(): BelongsTo
    {
        return $this->belongsTo(ProprietaireVersion::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
