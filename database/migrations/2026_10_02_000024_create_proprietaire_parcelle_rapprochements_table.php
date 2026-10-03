<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. Résultat du rapprochement texte entre l'adresse du
     * propriétaire ("Points Adresses - base locale" + code INSEE) et le
     * référentiel BAN (adresse_versions). `parcelle_id` est la clé métier
     * (pas une version précise) : cohérent avec installation_parcelle_evenements.
     */
    public function up(): void
    {
        Schema::create('proprietaire_parcelle_rapprochements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proprietaire_version_id')->constrained('proprietaire_versions');
            $table->string('parcelle_id', 20);
            $table->foreignId('adresse_version_id')->constrained('adresse_versions');
            $table->string('methode');
            $table->float('confiance');
            $table->foreignId('import_batch_id')->constrained('import_batches');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['proprietaire_version_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proprietaire_parcelle_rapprochements');
    }
};
