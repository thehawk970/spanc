<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Projection jetable du dernier rapprochement par (compteur, parcelle). Reconstructible. */
    public function up(): void
    {
        Schema::create('compteur_parcelle_actuel', function (Blueprint $table) {
            $table->id();
            $table->string('numero_compteur');
            $table->string('parcelle_id');
            $table->float('confiance')->nullable();
            $table->foreignId('rapprochement_id')->constrained('compteur_parcelle_rapprochements');
            $table->timestamp('maj_le');

            $table->unique(['numero_compteur', 'parcelle_id']);
            $table->index('parcelle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compteur_parcelle_actuel');
    }
};
