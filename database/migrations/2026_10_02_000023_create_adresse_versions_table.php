<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. Référentiel BAN (Base Adresse Nationale), filtré aux
     * communes déjà présentes dans parcelle_versions (périmètre de l'EPCI).
     * `cad_parcelles` vient directement de la BAN (lien officiel
     * adresse -> parcelle maintenu par l'IGN/DGFiP) : pas besoin de
     * géocodage ni de point-dans-polygone pour ce rapprochement.
     */
    public function up(): void
    {
        Schema::create('adresse_versions', function (Blueprint $table) {
            $table->id();
            $table->string('id_ban')->index();
            $table->string('numero', 10)->nullable();
            $table->string('repetition', 10)->nullable();
            $table->string('nom_voie');
            $table->string('nom_voie_normalise')->index();
            $table->string('code_postal', 5)->nullable();
            $table->string('code_insee', 5);
            $table->string('nom_commune')->nullable();
            $table->double('lon')->nullable();
            $table->double('lat')->nullable();
            $table->json('cad_parcelles')->nullable();
            $table->json('proprietes_brutes')->nullable();
            $table->foreignId('import_batch_id')->constrained('import_batches');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['code_insee', 'nom_voie_normalise', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adresse_versions');
    }
};
