<?php

namespace App\Models;

use App\Observers\InstallationEtatObserver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallationEtat extends ModeleImmuable
{
    protected static function booted(): void
    {
        static::observe(InstallationEtatObserver::class);
    }

    protected $fillable = [
        'installation_id', 'type', 'statut', 'metadata', 'motif', 'annule_evenement_id', 'auteur_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function evenementAnnule(): BelongsTo
    {
        return $this->belongsTo(self::class, 'annule_evenement_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
