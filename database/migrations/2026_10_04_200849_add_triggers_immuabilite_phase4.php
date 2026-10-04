<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Même garde-fou que les vagues précédentes de tables append-only. */
    private array $tablesImmuables = [
        'logement_hors_ass_coll_versions',
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
