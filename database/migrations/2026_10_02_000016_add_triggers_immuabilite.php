<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Défense en profondeur : même un bug applicatif ou une requête manuelle
     * ne peut pas modifier ou supprimer une ligne de ces tables append-only.
     * La discipline Eloquent (modèles sans update()/delete()) reste la
     * première ligne de défense ; ceci est le filet de sécurité au niveau
     * base de données.
     */
    private array $tablesImmuables = [
        'parcelle_versions',
        'batiment_versions',
        'proprietaire_versions',
        'compteur_versions',
        'batiment_parcelle_rapprochements',
        'installations',
        'installation_etats',
        'installation_parcelle_evenements',
        'installation_batiment_evenements',
        'installation_compteur_evenements',
        'installation_proprietaire_evenements',
        'rapports',
        'rapprochements_proposes',
        'rapprochements_decisions',
    ];

    public function up(): void
    {
        foreach ($this->tablesImmuables as $table) {
            DB::unprepared(<<<SQL
                CREATE TRIGGER {$table}_immuable_no_update
                BEFORE UPDATE ON {$table}
                BEGIN
                    SELECT RAISE(ABORT, '{$table} est immuable : UPDATE interdit, inserer un nouvel evenement');
                END;
            SQL);

            DB::unprepared(<<<SQL
                CREATE TRIGGER {$table}_immuable_no_delete
                BEFORE DELETE ON {$table}
                BEGIN
                    SELECT RAISE(ABORT, '{$table} est immuable : DELETE interdit');
                END;
            SQL);
        }
    }

    public function down(): void
    {
        foreach ($this->tablesImmuables as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_immuable_no_update");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_immuable_no_delete");
        }
    }
};
