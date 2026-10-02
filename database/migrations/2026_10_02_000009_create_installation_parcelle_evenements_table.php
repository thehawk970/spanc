<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. `parcelle_id` est la clé métier (code cadastral), pas une
     * clé étrangère vers une version précise : le lien reste valide même si
     * on n'a pas (encore) réimporté cette parcelle, ou si une version plus
     * récente existe.
     */
    public function up(): void
    {
        Schema::create('installation_parcelle_evenements', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('installation_id')->constrained('installations');
            $table->string('parcelle_id', 20);
            $table->string('action'); // lier | delier
            $table->string('source'); // auto | manuel
            $table->float('confiance')->nullable();
            $table->string('motif')->nullable();
            $table->foreignId('auteur_id')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['installation_id', 'created_at']);
            $table->index(['parcelle_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_parcelle_evenements');
    }
};
