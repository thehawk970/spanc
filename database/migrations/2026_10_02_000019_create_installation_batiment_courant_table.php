<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Projection jetable des liens bâtiment actuellement actifs (many-to-many). */
    public function up(): void
    {
        Schema::create('installation_batiment_courant', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('installation_id')->constrained('installations');
            $table->foreignId('batiment_version_id')->constrained('batiment_versions');
            $table->float('confiance')->nullable();
            $table->foreignId('evenement_id')->constrained('installation_batiment_evenements');
            $table->timestamp('maj_le');

            $table->unique(['installation_id', 'batiment_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_batiment_courant');
    }
};
