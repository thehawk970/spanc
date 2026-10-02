<?php

namespace App\Models;

use App\Observers\BatimentParcelleRapprochementObserver;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatimentParcelleRapprochement extends ModeleImmuable
{
    protected static function booted(): void
    {
        static::observe(BatimentParcelleRapprochementObserver::class);
    }

    protected $fillable = [
        'batiment_version_id', 'parcelle_version_id', 'methode', 'confiance', 'import_batch_id',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function batimentVersion(): BelongsTo
    {
        return $this->belongsTo(BatimentVersion::class);
    }

    public function parcelleVersion(): BelongsTo
    {
        return $this->belongsTo(ParcelleVersion::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
