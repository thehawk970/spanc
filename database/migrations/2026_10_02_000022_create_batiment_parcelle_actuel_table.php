<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projection jetable : meilleur candidat parcelle pour chaque version de
     * bâtiment, issue de batiment_parcelle_rapprochements. Reconstructible.
     */
    public function up(): void
    {
        Schema::create('batiment_parcelle_actuel', function (Blueprint $table) {
            $table->foreignId('batiment_version_id')->primary()->constrained('batiment_versions');
            $table->foreignId('parcelle_version_id')->constrained('parcelle_versions');
            $table->float('confiance');
            $table->foreignId('rapprochement_id')->constrained('batiment_parcelle_rapprochements');
            $table->timestamp('maj_le');

            $table->index('parcelle_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batiment_parcelle_actuel');
    }
};
