<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. Export d'un deuxieme logiciel de diagnostic SPANC (hors
     * Perigeo, voir dispositif_spanc_versions/controle_versions) : fiches
     * détaillées (infos générales + conclusion), une ligne par controle.
     *
     * Liee a la parcelle uniquement, jamais au proprietaire : ces fiches
     * remontent parfois a plusieurs annees, le proprietaire source peut ne
     * plus etre l'actuel. parcelle_ids (tableau, comme
     * adresse_versions.cad_parcelles) car "Section et numero(s) de
     * parcelle(s)" peut lister plusieurs parcelles par fiche
     * ("AI n°46-47-49-402-403").
     *
     * Pas de code INSEE dans la source : derive de "Ville (terrain)" (texte
     * libre tres variable - accents, abreviations, anciennes communes
     * fusionnees, fautes de frappe) a l'import, voir
     * ImporterFichesSpancPyaCommand::resoudreCommune().
     */
    public function up(): void
    {
        Schema::create('fiche_spanc_pya_versions', function (Blueprint $table) {
            $table->id();
            $table->string('id_source')->nullable()->index();
            $table->date('date_controle')->nullable();
            $table->string('nature_dernier_controle')->nullable();
            $table->string('avis')->nullable();
            $table->string('conformite')->nullable();
            $table->string('ville_terrain_brute')->nullable();
            $table->string('code_insee_resolu')->nullable();
            $table->string('section_numero_brute')->nullable();
            $table->json('parcelle_ids')->nullable();
            $table->json('proprietes_brutes')->nullable();
            $table->foreignId('import_batch_id')->constrained('import_batches');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiche_spanc_pya_versions');
    }
};
