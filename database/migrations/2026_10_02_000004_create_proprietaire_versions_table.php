<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. Pas de clé métier stable pour un propriétaire (homonymes
     * possibles) : chaque import ou saisie manuelle crée une nouvelle version,
     * y compris pour la même personne physique réapparaissant plus tard.
     */
    public function up(): void
    {
        Schema::create('proprietaire_versions', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom')->nullable();
            $table->string('contact')->nullable();
            $table->json('proprietes_brutes')->nullable();
            $table->foreignId('import_batch_id')->constrained('import_batches');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proprietaire_versions');
    }
};
