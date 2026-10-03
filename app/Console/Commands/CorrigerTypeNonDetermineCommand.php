<?php

namespace App\Console\Commands;

use App\Models\InstallationEtat;
use App\Models\InstallationEtatCourant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Correction ponctuelle (additive) : le bootstrap présumait `non_collectif`
 * par défaut, alors que le type n'est pas réellement connu tant que la base
 * SOGEDO n'a pas été croisée. Remplace par `non_determine` via un nouvel
 * état pour toutes les installations encore à `a_statuer` — celles
 * manuellement confirmées par un agent (statut différent) ne sont pas
 * concernées.
 */
class CorrigerTypeNonDetermineCommand extends Command
{
    protected $signature = 'installations:corriger-type-non-determine';

    protected $description = "Remplace le type 'non_collectif' presume par 'non_determine' sur les installations encore a_statuer";

    public function handle(): int
    {
        $installations = InstallationEtatCourant::where('type', 'non_collectif')
            ->where('statut', 'a_statuer')
            ->pluck('installation_id');

        $this->info("{$installations->count()} installations a corriger (type presume, jamais confirme).");

        $corrigees = 0;

        $bar = $this->output->createProgressBar($installations->count());
        $bar->start();

        DB::transaction(function () use ($installations, $bar, &$corrigees) {
            foreach ($installations as $installationId) {
                $bar->advance();

                InstallationEtat::create([
                    'installation_id' => $installationId,
                    'type' => 'non_determine',
                    'statut' => 'a_statuer',
                    'motif' => "Correction : 'non_collectif' n'etait qu'une hypothese par defaut du bootstrap, jamais confirmee. Le type reste non determine tant que la base SOGEDO n'a pas ete croisee.",
                ]);

                $corrigees++;
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Termine : {$corrigees} installations corrigees.");

        return self::SUCCESS;
    }
}
