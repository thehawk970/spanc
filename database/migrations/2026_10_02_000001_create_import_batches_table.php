<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Journal des imports : table de plomberie opérationnelle (mutable,
     * le statut avance pendant l'exécution du job). Ce n'est pas un fait
     * métier soumis à l'immuabilité — ce qui compte pour l'audit, c'est le
     * contenu importé (les tables *_versions), pas l'avancement du job.
     */
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('source');
            $table->string('fichier_origine')->nullable();
            $table->foreignId('declenche_par_id')->nullable()->constrained('users');
            $table->string('statut')->default('en_cours');
            $table->unsignedInteger('nombre_lignes')->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamp('demarre_le')->nullable();
            $table->timestamp('termine_le')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
