<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only : une ligne par version importée d'une parcelle cadastrale.
     * `parcelle_id` est la clé métier stable (code cadastral 14 car.), mais
     * n'est PAS unique dans cette table puisqu'on conserve chaque version.
     */
    public function up(): void
    {
        Schema::create('parcelle_versions', function (Blueprint $table) {
            $table->id();
            $table->string('parcelle_id', 20)->index();
            $table->string('commune_insee', 5);
            $table->string('section', 2);
            $table->string('numero', 4);
            $table->json('geometry');
            $table->unsignedInteger('surface_m2')->nullable();
            $table->json('proprietes_brutes')->nullable();
            $table->foreignId('import_batch_id')->constrained('import_batches');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['parcelle_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcelle_versions');
    }
};
