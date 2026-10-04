<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Même besoin que civilite sur compteur_versions : le fichier logement
     * hors assainissement collectif colle la civilité au nom ("MME PAILLET
     * NATHALIE"). Colonne additive : les lignes déjà importées restent
     * inchangées (immuabilité), civilite y reste nulle.
     */
    public function up(): void
    {
        Schema::table('proprietaire_versions', function (Blueprint $table) {
            $table->string('civilite')->nullable()->after('prenom');
        });
    }

    public function down(): void
    {
        Schema::table('proprietaire_versions', function (Blueprint $table) {
            $table->dropColumn('civilite');
        });
    }
};
