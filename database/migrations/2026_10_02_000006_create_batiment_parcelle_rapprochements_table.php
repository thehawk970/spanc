<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. Résultat du rapprochement géométrique bâtiment <-> parcelle
     * (index en grille, cf. commande de rapprochement). Chaque exécution du
     * job ajoute des lignes ; on ne réécrit jamais un résultat précédent.
     */
    public function up(): void
    {
        Schema::create('batiment_parcelle_rapprochements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batiment_version_id')->constrained('batiment_versions');
            $table->foreignId('parcelle_version_id')->constrained('parcelle_versions');
            $table->string('methode');
            $table->float('confiance');
            $table->foreignId('import_batch_id')->constrained('import_batches');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['batiment_version_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batiment_parcelle_rapprochements');
    }
};
