<?php

namespace App\Console\Commands;

use App\Models\InstallationCompteurEvenement;
use App\Models\InstallationProprietaireEvenement;
use App\Models\RapprochementDecision;
use App\Models\RapprochementPropose;
use App\Support\DeterminationTypeSogedo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Valide automatiquement les propositions à confiance 1.0 (correspondance
 * BAN exacte). Plusieurs propriétaires candidats pour une même installation
 * ne sont PAS une ambiguïté à trancher : l'indivision, l'usufruit/nue-
 * propriété, etc. sont des situations réelles où plusieurs propriétaires
 * coexistent légitimement sur une même installation (many-to-many assumé
 * dès la conception). Pour les compteurs en revanche, la confiance est déjà
 * plafonnée en amont (installations:proposer-compteurs) dès qu'une parcelle
 * porte plusieurs installations : un compteur n'atteint 1.0 que si son
 * rattachement est sans ambiguïté. Seules les correspondances moins sûres
 * (< 1.0) restent dans la file de validation humaine.
 */
class ValiderAutomatiquementCommand extends Command
{
    protected $signature = 'rapprochements:valider-automatiques';

    protected $description = 'Valide automatiquement les propositions a confiance 1.0 (plusieurs proprietaires par installation autorises)';

    public function handle(): int
    {
        $propositions = RapprochementPropose::whereDoesntHave('decisions')
            ->where('confiance', '>=', 1.0)
            ->get();

        $this->info("{$propositions->count()} propositions a confiance 1.0 a valider automatiquement.");

        $validees = 0;
        $typesDetermines = 0;

        $bar = $this->output->createProgressBar($propositions->count());
        $bar->start();

        DB::transaction(function () use ($propositions, $bar, &$validees, &$typesDetermines) {
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

                if ($proposition->type_cible === 'compteur' && $proposition->installation_id) {
                    InstallationCompteurEvenement::create([
                        'installation_id' => $proposition->installation_id,
                        'numero_compteur' => $proposition->cible_id,
                        'action' => 'lier',
                        'source' => 'auto',
                        'confiance' => $proposition->confiance,
                        'motif' => "Validation automatique : correspondance BAN exacte, parcelle non partagee ({$proposition->methode})",
                    ]);

                    if (DeterminationTypeSogedo::depuisCompteursLies($proposition->installation_id)['resultat'] === 'determine') {
                        $typesDetermines++;
                    }
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

        $this->info("Termine : {$validees} propositions validees automatiquement, {$typesDetermines} type(s) AC/ANC deduit(s) au passage depuis le code redevance.");

        return self::SUCCESS;
    }
}
