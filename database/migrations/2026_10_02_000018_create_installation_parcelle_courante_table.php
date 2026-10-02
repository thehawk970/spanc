<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Projection jetable des liens parcelle actuellement actifs (many-to-many). */
    public function up(): void
    {
        Schema::create('installation_parcelle_courante', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('installation_id')->constrained('installations');
            $table->string('parcelle_id', 20);
            $table->float('confiance')->nullable();
            $table->foreignId('evenement_id')->constrained('installation_parcelle_evenements');
            $table->timestamp('maj_le');

            $table->unique(['installation_id', 'parcelle_id']);
            $table->index('parcelle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_parcelle_courante');
    }
};
