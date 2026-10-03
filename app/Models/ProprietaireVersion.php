<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProprietaireVersion extends ModeleImmuable
{
    protected $fillable = [
        'nom', 'prenom', 'contact', 'proprietes_brutes', 'import_batch_id',
    ];

    protected function casts(): array
    {
        return [
            'proprietes_brutes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    public function installationsCourantes(): HasMany
    {
        return $this->hasMany(InstallationProprietaireCourante::class);
    }

    public function parcellesActuelles(): HasMany
    {
        return $this->hasMany(ProprietaireParcelleActuel::class);
    }
}
