<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. Référence une version de propriétaire (pas de clé métier
     * fiable vu les homonymes possibles).
     */
    public function up(): void
    {
        Schema::create('installation_proprietaire_evenements', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('installation_id')->constrained('installations');
            $table->foreignId('proprietaire_version_id')->constrained('proprietaire_versions');
            $table->string('action'); // lier | delier
            $table->string('source'); // auto | manuel
            $table->float('confiance')->nullable();
            $table->string('motif')->nullable();
            $table->foreignId('auteur_id')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['installation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_proprietaire_evenements');
    }
};
