<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. Chaque ligne est un nouvel état complet de l'installation
     * (type, statut, métadonnées) à un instant donné. Corriger une erreur de
     * saisie = insérer un nouvel état avec `annule_evenement_id` pointant
     * vers celui qu'il corrige — jamais réécrire la ligne fautive.
     */
    public function up(): void
    {
        Schema::create('installation_etats', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('installation_id')->constrained('installations');
            $table->string('type'); // collectif | non_collectif
            $table->string('statut');
            $table->json('metadata')->nullable();
            $table->string('motif')->nullable();
            $table->foreignId('annule_evenement_id')->nullable()->constrained('installation_etats');
            $table->foreignId('auteur_id')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['installation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_etats');
    }
};
