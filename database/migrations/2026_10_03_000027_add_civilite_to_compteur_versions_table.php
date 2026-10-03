<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `abonne_nom_brut` mélangeait civilité (M., Mme, M. et Mme, M. ou Mme...)
     * et nom. Séparé pour que le nom reste exploitable et que la civilité
     * (utile pour reconnaître une indivision, ex. "M. ou Mme") ne soit pas
     * perdue. Colonne additive : les lignes déjà importées restent inchangées
     * (immuabilité), civilite y reste simplement nulle.
     */
    public function up(): void
    {
        Schema::table('compteur_versions', function (Blueprint $table) {
            $table->string('civilite')->nullable()->after('abonne_nom_brut');
        });
    }

    public function down(): void
    {
        Schema::table('compteur_versions', function (Blueprint $table) {
            $table->dropColumn('civilite');
        });
    }
};
