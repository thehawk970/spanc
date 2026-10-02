<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. `numero_compteur` (attribué par la SOGEDO) est la clé
     * métier stable réutilisée par les évènements de liaison.
     */
    public function up(): void
    {
        Schema::create('compteur_versions', function (Blueprint $table) {
            $table->id();
            $table->string('numero_compteur')->index();
            $table->text('adresse_brute')->nullable();
            $table->string('abonne_nom_brut')->nullable();
            $table->json('proprietes_brutes')->nullable();
            $table->foreignId('import_batch_id')->constrained('import_batches');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['numero_compteur', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compteur_versions');
    }
};
