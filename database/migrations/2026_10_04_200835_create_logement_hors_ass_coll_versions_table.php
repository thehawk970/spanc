<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. Export fiscal/cadastral (type MAJIC) des logements hors
     * assainissement collectif : parcelle_id est directement déductible de
     * l'idu (prefixe departement "24" + idu), pas besoin de rapprochement
     * geometrique/texte comme pour la BAN ou SOGEDO.
     */
    public function up(): void
    {
        Schema::create('logement_hors_ass_coll_versions', function (Blueprint $table) {
            $table->id();
            $table->string('parcelle_id')->index();
            $table->string('numero_proprietaire')->nullable();
            $table->string('proprietaire_nom_brut')->nullable();
            $table->string('type_habitation')->nullable();
            $table->unsignedSmallInteger('nombre_locaux')->nullable();
            $table->json('proprietes_brutes')->nullable();
            $table->foreignId('import_batch_id')->constrained('import_batches');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logement_hors_ass_coll_versions');
    }
};
