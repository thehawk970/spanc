<?php

namespace App\Models;

use App\Observers\CompteurParcelleRapprochementObserver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompteurParcelleRapprochement extends ModeleImmuable
{
    protected static function booted(): void
    {
        static::observe(CompteurParcelleRapprochementObserver::class);
    }

    protected $fillable = [
        'numero_compteur', 'parcelle_id', 'adresse_version_id', 'methode', 'confiance', 'import_batch_id',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function adresseVersion(): BelongsTo
    {
        return $this->belongsTo(AdresseVersion::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
