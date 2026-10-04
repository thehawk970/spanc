<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. Export du logiciel SPANC historique : une ligne par
     * dispositif deja suivi, avec le resume de son dernier controle (pas un
     * historique - voir controle_versions pour les evenements individuels).
     * section/numero sont extraits de "Reference cadastrale" (ex. "AB0134"
     * -> section "AB", numero "134", sans zeros de tete comme stocke dans
     * parcelle_versions) : matche 98% des lignes sur notre cadastre deja
     * importe, plus fiable qu'une reconstruction de parcelle_id (le prefixe
     * cadastral n'est pas toujours "0000", ex. Pays de Belves).
     */
    public function up(): void
    {
        Schema::create('dispositif_spanc_versions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_dossier')->index();
            $table->string('code_insee')->nullable();
            $table->string('section')->nullable();
            $table->string('numero')->nullable();
            $table->date('date_derniere_visite')->nullable();
            $table->string('technicien')->nullable();
            $table->string('nature_dernier_controle')->nullable();
            $table->string('avis_dernier_controle')->nullable();
            $table->string('type_filiere')->nullable();
            $table->string('nom_cadastre')->nullable();
            $table->string('prenom_cadastre')->nullable();
            $table->string('nom_spanc')->nullable();
            $table->string('prenom_spanc')->nullable();
            $table->json('proprietes_brutes')->nullable();
            $table->foreignId('import_batch_id')->constrained('import_batches');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['code_insee', 'section', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositif_spanc_versions');
    }
};
