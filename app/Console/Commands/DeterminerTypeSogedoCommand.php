<?php

namespace App\Console\Commands;

use App\Models\InstallationEtatCourant;
use App\Support\DeterminationTypeSogedo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sweep de rattrapage : la détermination se fait normalement en temps réel
 * à chaque liaison compteur (voir DeterminationTypeSogedo), mais cette
 * commande reste utile pour les installations liées avant l'ajout de ce
 * comportement, ou après un rejeu de projections.
 */
class DeterminerTypeSogedoCommand extends Command
{
    protected $signature = 'installations:determiner-type-sogedo';

    protected $description = 'Deduit le type (collectif/non collectif) depuis le code redevance des compteurs lies, pour les installations non determinees';

    public function handle(): int
    {
        $installationIds = InstallationEtatCourant::where('type', 'non_determine')->pluck('installation_id');

        $this->info("{$installationIds->count()} installations non determinees a examiner.");

        $compteurs = ['determine' => 0, 'ambigu' => 0, 'sans_code' => 0, 'sans_compteur' => 0];

        $bar = $this->output->createProgressBar($installationIds->count());
        $bar->start();

        DB::transaction(function () use ($installationIds, $bar, &$compteurs) {
            foreach ($installationIds as $installationId) {
                $bar->advance();

                $resultat = DeterminationTypeSogedo::depuisCompteursLies($installationId)['resultat'];
                $compteurs[$resultat] = ($compteurs[$resultat] ?? 0) + 1;
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Termine : {$compteurs['determine']} types determines, {$compteurs['ambigu']} ambigus (codes contradictoires, non tranches), {$compteurs['sans_code']} sans code exploitable, {$compteurs['sans_compteur']} sans compteur lie.");

        return self::SUCCESS;
    }
}
