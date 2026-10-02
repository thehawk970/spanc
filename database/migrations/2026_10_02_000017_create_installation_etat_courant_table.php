<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projection jetable : aucune information ici qui ne soit pas déjà dans
     * installation_etats. Reconstructible à tout moment (artisan
     * projections:rebuild). Mutable par construction — ce n'est pas une
     * source de vérité, donc pas de trigger d'immuabilité dessus.
     */
    public function up(): void
    {
        Schema::create('installation_etat_courant', function (Blueprint $table) {
            $table->foreignUuid('installation_id')->primary()->constrained('installations');
            $table->string('type');
            $table->string('statut');
            $table->json('metadata')->nullable();
            $table->foreignId('evenement_id')->constrained('installation_etats');
            $table->timestamp('maj_le');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_etat_courant');
    }
};
