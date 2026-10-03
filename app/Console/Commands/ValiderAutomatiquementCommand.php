<?php

namespace App\Console\Commands;

use App\Models\InstallationProprietaireEvenement;
use App\Models\RapprochementDecision;
use App\Models\RapprochementPropose;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Valide automatiquement les propositions à confiance 1.0 (correspondance
 * BAN exacte). Plusieurs propriétaires candidats pour une même installation
 * ne sont PAS une ambiguïté à trancher : l'indivision, l'usufruit/nue-
 * propriété, etc. sont des situations réelles où plusieurs propriétaires
 * coexistent légitimement sur une même installation (many-to-many assumé
 * dès la conception). Seules les correspondances moins sûres (< 1.0)
 * restent dans la file de validation humaine.
 */
class ValiderAutomatiquementCommand extends Command
{
    protected $signature = 'rapprochements:valider-automatiques';

    protected $description = "Valide automatiquement les propositions a confiance 1.0 (plusieurs proprietaires par installation autorises)";

    public function handle(): int
    {
        $propositions = RapprochementPropose::whereDoesntHave('decisions')
            ->where('confiance', '>=', 1.0)
            ->get();

        $this->info("{$propositions->count()} propositions a confiance 1.0 a valider automatiquement.");

        $validees = 0;

        $bar = $this->output->createProgressBar($propositions->count());
        $bar->start();

        DB::transaction(function () use ($propositions, $bar, &$validees) {
            foreach ($propositions as $proposition) {
                $bar->advance();

                if ($proposition->type_cible === 'proprietaire' && $proposition->installation_id) {
                    InstallationProprietaireEvenement::create([
                        'installation_id' => $proposition->installation_id,
                        'proprietaire_version_id' => $proposition->cible_id,
                        'action' => 'lier',
                        'source' => 'auto',
                        'confiance' => $proposition->confiance,
                        'motif' => "Validation automatique : correspondance BAN exacte ({$proposition->methode})",
                    ]);
                }

                RapprochementDecision::create([
                    'rapprochement_propose_id' => $proposition->id,
                    'decision' => 'valide',
                    'motif' => 'Validation automatique (confiance 1.0) : plusieurs propriétaires autorisés par installation (indivision, usufruit/nue-propriété...)',
                ]);

                $validees++;
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Termine : {$validees} propositions validees automatiquement.");

        return self::SUCCESS;
    }
}
