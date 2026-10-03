<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projection jetable : meilleur(s) candidat(s) parcelle par propriétaire.
     * Un propriétaire peut légitimement avoir plusieurs parcelles (adresse
     * couvrant plusieurs parcelles cadastrales) : many-to-many comme les
     * autres projections, pas de ligne unique par propriétaire.
     */
    public function up(): void
    {
        Schema::create('proprietaire_parcelle_actuel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proprietaire_version_id')->constrained('proprietaire_versions');
            $table->string('parcelle_id', 20);
            $table->float('confiance');
            $table->foreignId('rapprochement_id')->constrained('proprietaire_parcelle_rapprochements');
            $table->timestamp('maj_le');

            $table->unique(['proprietaire_version_id', 'parcelle_id']);
            $table->index('parcelle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proprietaire_parcelle_actuel');
    }
};
