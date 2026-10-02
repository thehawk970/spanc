<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. La source (BDTopo via cadastre.data.gouv.fr) ne fournit
     * pas d'identifiant stable pour un bâtiment : `cle_dedup` (hash de la
     * géométrie arrondie + commune) sert à repérer qu'un même bâtiment
     * réapparaît à l'identique lors d'un réimport.
     * Le centroïde et la bounding box sont précalculés à l'import pour
     * servir d'index spatial lors du rapprochement avec les parcelles.
     */
    public function up(): void
    {
        Schema::create('batiment_versions', function (Blueprint $table) {
            $table->id();
            $table->string('cle_dedup', 64)->index();
            $table->string('commune_insee', 5);
            $table->string('type')->nullable();
            $table->string('nom')->nullable();
            $table->json('geometry');
            $table->double('centroide_lon');
            $table->double('centroide_lat');
            $table->double('bbox_min_lon');
            $table->double('bbox_max_lon');
            $table->double('bbox_min_lat');
            $table->double('bbox_max_lat');
            $table->foreignId('import_batch_id')->constrained('import_batches');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['cle_dedup', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batiment_versions');
    }
};
