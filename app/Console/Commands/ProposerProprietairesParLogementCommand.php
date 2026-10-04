<?php

namespace App\Console\Commands;

use App\Models\InstallationParcelleCourante;
use App\Models\InstallationProprietaireCourante;
use App\Models\LogementHorsAssCollVersion;
use App\Models\ProprietaireVersion;
use App\Models\RapprochementPropose;
use App\Support\CorrespondanceNom;
use Illuminate\Console\Command;

/**
 * Source secondaire de rapprochement propriétaire (la BAN reste la source
 * principale du bootstrap des installations, voir
 * BootstrapInstallationsDepuisAdressesCommand). L'export fiscal/cadastral
 * des logements hors assainissement collectif n'a pas d'adresse BAN
 * exploitable (lieux-dits, adresse cadastrale brute), mais donne un lien
 * parcelle exact (idu) et un propriétaire fiscal — utile pour confirmer ou
 * compléter un propriétaire déjà proposé via la BAN, ou en proposer un là où
 * la BAN n'en a trouvé aucun.
 *
 * Contrairement à installations:proposer-compteurs-par-nom, pas besoin de
 * rapprocher la parcelle au préalable : parcelle_id est déjà exact à
 * l'import (idu). Seul le nom du propriétaire reste à faire correspondre au
 * référentiel propriétaires existant (personnes morales — SCI, SARL,
 * commune... — n'y trouveront jamais de correspondance, c'est attendu).
 */
class ProposerProprietairesParLogementCommand extends Command
{
    protected $signature = 'installations:proposer-proprietaires-par-logement';

    protected $description = "Propose de lier le proprietaire fiscal d'un logement (export cadastre hors assainissement collectif) a l'installation qui porte la meme parcelle";

    private const CONFIANCE = 0.7;

    public function handle(): int
    {
        $proprietaires = ProprietaireVersion::all(['id', 'nom', 'prenom'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'nom_tokens' => CorrespondanceNom::tokens($p->nom),
                'prenom_tokens' => CorrespondanceNom::tokens($p->prenom ?? ''),
            ]);

        $this->info("{$proprietaires->count()} proprietaires connus.");

        $logements = LogementHorsAssCollVersion::all(['parcelle_id', 'proprietaire_nom_brut']);

        $this->info("{$logements->count()} logements a examiner.");

        $creees = 0;
        $dejaLiees = 0;
        $dejaProposees = 0;
        $sansInstallation = 0;
        $sansCandidat = 0;

        $bar = $this->output->createProgressBar($logements->count());
        $bar->start();

        foreach ($logements as $logement) {
            $bar->advance();

            if (! $logement->proprietaire_nom_brut) {
                $sansCandidat++;

                continue;
            }

            $installationIds = InstallationParcelleCourante::where('parcelle_id', $logement->parcelle_id)
                ->pluck('installation_id');

            if ($installationIds->isEmpty()) {
                $sansInstallation++;

                continue;
            }

            $tokensProprietaire = CorrespondanceNom::tokens($logement->proprietaire_nom_brut);

            $candidatTrouve = false;

            foreach ($proprietaires as $proprietaire) {
                $restants = CorrespondanceNom::sansSousSequence($tokensProprietaire, $proprietaire['nom_tokens']);

                if ($restants === null || $restants === [] || $proprietaire['prenom_tokens'] === []) {
                    continue;
                }

                if (array_intersect($restants, $proprietaire['prenom_tokens']) === []) {
                    continue;
                }

                $candidatTrouve = true;

                foreach ($installationIds as $installationId) {
                    $dejaLie = InstallationProprietaireCourante::where('installation_id', $installationId)
                        ->where('proprietaire_version_id', $proprietaire['id'])
                        ->exists();

                    if ($dejaLie) {
                        $dejaLiees++;

                        continue;
                    }

                    $dejaPropose = RapprochementPropose::where('installation_id', $installationId)
                        ->where('type_cible', 'proprietaire')
                        ->where('cible_id', (string) $proprietaire['id'])
                        ->exists();

                    if ($dejaPropose) {
                        $dejaProposees++;

                        continue;
                    }

                    RapprochementPropose::create([
                        'installation_id' => $installationId,
                        'type_cible' => 'proprietaire',
                        'cible_id' => (string) $proprietaire['id'],
                        'methode' => 'logement_cadastre_nom_prenom',
                        'confiance' => self::CONFIANCE,
                    ]);
                    $creees++;
                }
            }

            if (! $candidatTrouve) {
                $sansCandidat++;
            }
        }

        $bar->finish();
        $this->newLine();

        $this->info("Termine : {$creees} nouvelles propositions, {$dejaLiees} deja liees, {$dejaProposees} deja proposees, {$sansInstallation} logements sans installation sur leur parcelle, {$sansCandidat} sans candidat nom/prenom.");

        return self::SUCCESS;
    }
}
