<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Projection jetable des propriétaires actuellement liés (co-propriété possible). */
    public function up(): void
    {
        Schema::create('installation_proprietaire_courante', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('installation_id')->constrained('installations');
            $table->foreignId('proprietaire_version_id')->constrained('proprietaire_versions');
            $table->foreignId('evenement_id')->constrained('installation_proprietaire_evenements');
            $table->timestamp('maj_le');

            $table->unique(['installation_id', 'proprietaire_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_proprietaire_courante');
    }
};
