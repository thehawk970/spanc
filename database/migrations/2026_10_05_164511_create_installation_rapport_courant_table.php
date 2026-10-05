<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projection jetable : aucune information ici qui ne soit pas déjà dans
     * rapports. Reconstructible à tout moment (artisan
     * projections:rebuild). Mutable par construction — ce n'est pas une
     * source de vérité, donc pas de trigger d'immuabilité dessus.
     *
     * Toujours le rapport le plus récent par date_controle, pas par ordre
     * d'insertion (les rapports sont souvent importés en masse, dans un
     * ordre qui n'est pas chronologique) — voir RapportObserver.
     */
    public function up(): void
    {
        Schema::create('installation_rapport_courant', function (Blueprint $table) {
            $table->foreignUuid('installation_id')->primary()->constrained('installations');
            $table->foreignId('rapport_id')->constrained('rapports');
            $table->string('type_controle');
            $table->date('date_controle');
            $table->string('conclusion')->nullable();
            $table->timestamp('maj_le');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_rapport_courant');
    }
};
