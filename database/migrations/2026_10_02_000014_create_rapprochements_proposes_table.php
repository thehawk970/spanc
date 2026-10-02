<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. Une proposition générée automatiquement (candidat de
     * rapprochement) n'est jamais modifiée : la décision (valider/rejeter)
     * est enregistrée séparément dans rapprochements_decisions.
     * `installation_id` peut être nul : une proposition peut concerner un
     * bâtiment/compteur pas encore rattaché à une installation connue.
     */
    public function up(): void
    {
        Schema::create('rapprochements_proposes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('installation_id')->nullable()->constrained('installations');
            $table->string('type_cible'); // parcelle | batiment | compteur | proprietaire
            $table->string('cible_id'); // valeur générique : parcelle_id, numero_compteur, ou id de version selon type_cible
            $table->string('methode');
            $table->float('confiance');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['type_cible', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapprochements_proposes');
    }
};
