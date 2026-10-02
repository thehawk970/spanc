<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. `numero_compteur` (clé SOGEDO) est la clé métier utilisée
     * directement, même raisonnement que pour parcelle_id.
     */
    public function up(): void
    {
        Schema::create('installation_compteur_evenements', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('installation_id')->constrained('installations');
            $table->string('numero_compteur');
            $table->string('action'); // lier | delier
            $table->string('source'); // auto | manuel
            $table->float('confiance')->nullable();
            $table->string('motif')->nullable();
            $table->foreignId('auteur_id')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['installation_id', 'created_at']);
            $table->index(['numero_compteur', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_compteur_evenements');
    }
};
