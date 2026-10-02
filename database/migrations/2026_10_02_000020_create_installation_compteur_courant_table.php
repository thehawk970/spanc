<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Projection jetable des liens compteur actuellement actifs (many-to-many). */
    public function up(): void
    {
        Schema::create('installation_compteur_courant', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('installation_id')->constrained('installations');
            $table->string('numero_compteur');
            $table->float('confiance')->nullable();
            $table->foreignId('evenement_id')->constrained('installation_compteur_evenements');
            $table->timestamp('maj_le');

            $table->unique(['installation_id', 'numero_compteur']);
            $table->index('numero_compteur');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_compteur_courant');
    }
};
