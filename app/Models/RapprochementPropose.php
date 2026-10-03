<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RapprochementPropose extends ModeleImmuable
{
    protected $table = 'rapprochements_proposes';

    protected $fillable = [
        'installation_id', 'type_cible', 'cible_id', 'methode', 'confiance',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(RapprochementDecision::class, 'rapprochement_propose_id');
    }

    /** Dernière décision prise (ou null = encore en attente). Calculé, jamais stocké. */
    public function derniereDecision(): ?RapprochementDecision
    {
        return $this->decisions()->latest('created_at')->first();
    }
}
