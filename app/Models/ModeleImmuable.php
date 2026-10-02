<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Base des tables append-only. Les triggers SQLite sont le filet de sécurité
 * final ; ce garde-fou applicatif fait échouer vite et clairement toute
 * tentative d'UPDATE/DELETE écrite par erreur dans le code.
 */
abstract class ModeleImmuable extends Model
{
    const UPDATED_AT = null;

    protected function performUpdate(Builder $query): bool
    {
        if (! $this->exists) {
            return parent::performUpdate($query);
        }

        throw new LogicException(static::class.' est immuable : UPDATE interdit, inserer une nouvelle ligne.');
    }

    public function delete(): ?bool
    {
        throw new LogicException(static::class.' est immuable : DELETE interdit.');
    }
}
