<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only. Fusionne les exports "ctrl_be" (verification de bonne
     * execution, post-travaux) et "ctrl_dpv" (diagnostic/vente/periodique) :
     * memes colonnes a quelques noms pres (voir
     * ImporterControlesCommand), type_source distingue la source. Un vrai
     * historique d'evenements (contrairement a dispositif_spanc, qui ne
     * garde que le dernier controle) : plusieurs lignes peuvent viser le
     * meme dossier.
     *
     * reference_dossier_liee est extraite de "Lien vers le dossier" (motif
     * "{code_insee}_{section+numero}_{id}" embarque dans le texte) et
     * correspond a dispositif_spanc_versions.reference_dossier dans ~85%
     * des cas (be) : seul pont fiable, l'adresse brute ne suffit pas pour
     * un rapprochement exact.
     */
    public function up(): void
    {
        Schema::create('controle_versions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_controle')->nullable()->index();
            $table->string('type_source'); // be | dpv
            $table->string('reference_dossier_liee')->nullable()->index();
            $table->string('commune')->nullable();
            $table->string('cadre_ou_type')->nullable();
            $table->string('type_filiere')->nullable();
            $table->text('lien_dossier')->nullable();
            $table->date('date_visite')->nullable();
            $table->string('technicien')->nullable();
            $table->string('etat_controle')->nullable();
            $table->string('avis')->nullable();
            $table->string('proprietaire_brut')->nullable();
            $table->string('usager_brut')->nullable();
            $table->text('adresse_parcelle_brute')->nullable();
            $table->text('adresse_proprietaire_brute')->nullable();
            $table->json('proprietes_brutes')->nullable();
            $table->foreignId('import_batch_id')->constrained('import_batches');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('controle_versions');
    }
};
