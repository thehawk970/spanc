<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RapprochementDecision extends ModeleImmuable
{
    protected $table = 'rapprochements_decisions';

    protected $fillable = [
        'rapprochement_propose_id', 'decision', 'motif', 'decide_par_id',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function rapprochementPropose(): BelongsTo
    {
        return $this->belongsTo(RapprochementPropose::class);
    }

    public function decidePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decide_par_id');
    }
}
