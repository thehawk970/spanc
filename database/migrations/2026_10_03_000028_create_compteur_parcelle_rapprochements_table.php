<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. Même principe que proprietaire_parcelle_rapprochements :
     * l'adresse abonné SOGEDO (brute, non géocodée) est rapprochée d'une
     * adresse BAN, dont `cad_parcelles` donne la/les parcelle(s).
     */
    public function up(): void
    {
        Schema::create('compteur_parcelle_rapprochements', function (Blueprint $table) {
            $table->id();
            $table->string('numero_compteur')->index();
            $table->string('parcelle_id')->index();
            $table->foreignId('adresse_version_id')->nullable()->constrained('adresse_versions');
            $table->string('methode');
            $table->float('confiance');
            $table->foreignId('import_batch_id')->constrained('import_batches');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['numero_compteur', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compteur_parcelle_rapprochements');
    }
};
