<?php

namespace App\Models;

use App\Observers\ProprietaireParcelleRapprochementObserver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProprietaireParcelleRapprochement extends ModeleImmuable
{
    protected static function booted(): void
    {
        static::observe(ProprietaireParcelleRapprochementObserver::class);
    }

    protected $fillable = [
        'proprietaire_version_id', 'parcelle_id', 'adresse_version_id', 'methode', 'confiance', 'import_batch_id',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function proprietaireVersion(): BelongsTo
    {
        return $this->belongsTo(ProprietaireVersion::class);
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
