<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. La décision humaine sur une proposition de rapprochement.
     * Statut courant d'une proposition = dernière décision associée (ou
     * absence de décision = en attente) ; calculé, jamais stocké sur la
     * proposition elle-même.
     */
    public function up(): void
    {
        Schema::create('rapprochements_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapprochement_propose_id')->constrained('rapprochements_proposes');
            $table->string('decision'); // valide | rejete
            $table->string('motif')->nullable();
            $table->foreignId('decide_par_id')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['rapprochement_propose_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapprochements_decisions');
    }
};
