<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * L'ancre stable du système : un UUID qui ne porte quasiment aucun
     * attribut mutable. Le type, le statut et les métadonnées vivent dans
     * installation_etats (append-only) ; ce qu'on voit ici ne change jamais
     * après création.
     */
    public function up(): void
    {
        Schema::create('installations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('cree_par_id')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installations');
    }
};
