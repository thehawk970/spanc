<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remet à zéro TOUTES les installations et ce qui en découle (états,
 * évènements, rapports, propositions/décisions, projections). Les
 * référentiels importés (cadastre, BAN, propriétaires) et les rapprochements
 * géométriques/adresse ne sont PAS touchés — ils restent coûteux à
 * recalculer et ne sont pas en cause.
 *
 * Nécessaire uniquement parce qu'on est encore en phase de calibration du
 * bootstrap : une fois de vraies données SPANC en place, cette commande n'a
 * plus sa place (elle contredirait le principe d'immuabilité). Désactive
 * temporairement les triggers d'immuabilité pour pouvoir vider les tables,
 * puis les recrée à l'identique.
 */
class ResetInstallationsCommand extends Command
{
    protected $signature = 'installations:reset {--force}';

    protected $description = 'DESTRUCTEUR (dev only) : vide installations et tout ce qui en decoule, sans toucher au cadastre/BAN/proprietaires importes';

    private array $tablesAppendOnly = [
        'rapprochements_decisions',
        'rapprochements_proposes',
        'rapports',
        'installation_proprietaire_evenements',
        'installation_compteur_evenements',
        'installation_batiment_evenements',
        'installation_parcelle_evenements',
        'installation_etats',
        'installations',
    ];

    private array $projections = [
        'installation_etat_courant',
        'installation_parcelle_courante',
        'installation_batiment_courant',
        'installation_compteur_courant',
        'installation_proprietaire_courante',
    ];

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Ceci supprime TOUTES les installations (et leur historique). Continuer ?')) {
            return self::FAILURE;
        }

        DB::statement('PRAGMA foreign_keys = OFF');

        foreach ($this->tablesAppendOnly as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_immuable_no_update");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_immuable_no_delete");
        }

        foreach ([...$this->tablesAppendOnly, ...$this->projections] as $table) {
            DB::table($table)->delete();
            $this->line("Videe : {$table}");
        }

        foreach ($this->tablesAppendOnly as $table) {
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

        DB::statement('PRAGMA foreign_keys = ON');

        $this->info('Installations reinitialisees. Triggers d\'immuabilite restaures.');

        return self::SUCCESS;
    }
}
