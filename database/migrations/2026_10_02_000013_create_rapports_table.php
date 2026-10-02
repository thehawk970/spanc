<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. Un rapport de visite SPANC est déjà par nature un fait
     * daté et immuable ; une correction se fait via `corrige_rapport_id`,
     * jamais en réécrivant le rapport original.
     */
    public function up(): void
    {
        Schema::create('rapports', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('installation_id')->constrained('installations');
            $table->string('type_controle'); // diagnostic_initial | controle_periodique | controle_conception | controle_realisation | controle_vente
            $table->date('date_controle');
            $table->string('conclusion')->nullable(); // conforme | non_conforme | avec_reserves
            $table->string('document_path')->nullable();
            $table->text('commentaire')->nullable();
            $table->foreignId('corrige_rapport_id')->nullable()->constrained('rapports');
            $table->foreignId('auteur_id')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['installation_id', 'date_controle']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapports');
    }
};
