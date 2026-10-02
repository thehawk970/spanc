<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rapport extends ModeleImmuable
{
    protected $fillable = [
        'installation_id', 'type_controle', 'date_controle', 'conclusion',
        'document_path', 'commentaire', 'corrige_rapport_id', 'auteur_id',
    ];

    protected function casts(): array
    {
        return [
            'date_controle' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function installation(): BelongsTo
    {
        return $this->belongsTo(Installation::class);
    }

    public function rapportCorrige(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrige_rapport_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
