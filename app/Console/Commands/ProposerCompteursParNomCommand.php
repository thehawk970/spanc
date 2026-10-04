<?php

namespace App\Console\Commands;

use App\Models\CompteurVersion;
use App\Models\InstallationCompteurCourant;
use App\Models\RapprochementPropose;
use App\Support\CorrespondanceNom;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Complément à installations:proposer-compteurs (qui passe par la parcelle) :
 * certains compteurs ont une adresse de facturation qui ne correspond à
 * aucune voie BAN connue (lieux-dits ruraux typiquement : "LIEU-DIT
 * GRATECAP" côté SOGEDO, "Route de Gratecap" côté BAN — aucun point commun
 * textuel) et restent donc orphelins de parcelle, même si le nom de
 * l'abonné suffit à identifier sans ambiguïté le propriétaire.
 *
 * Rapproche ici par nom + prénom, nom et prénom étant deux champs distincts
 * côté propriétaire (BAN pro) mais un seul champ libre côté SOGEDO
 * ("PISTOLOZZI MELANIE"). Le prénom SOGEDO n'est souvent qu'un des prénoms
 * déclarés ("MELANIE" sur le compteur vs "MELANIE MARINA" chez le
 * propriétaire, parfois même le 2e/3e prénom utilisé comme prénom usuel) :
 * le nom doit matcher en bloc (sous-séquence de mots), le prénom se
 * contente d'un mot en commun entre les deux listes. Le tiret des prénoms
 * composés est volontairement conservé dans le découpage (pas remplacé par
 * un espace comme Adressage::normaliser le ferait) : "JEAN-MICHEL" et
 * "JEAN-PAUL" ne doivent pas matcher via leur seul "JEAN" commun, trop
 * fréquent pour être un signal fiable.
 *
 * Toujours moins fiable qu'un rapprochement géométrique : confiance
 * plafonnée sous 1.0 pour forcer une validation humaine (même file que les
 * autres propositions, Filament ou /rapprochements).
 */
class ProposerCompteursParNomCommand extends Command
{
    protected $signature = 'installations:proposer-compteurs-par-nom';

    protected $description = "Propose de lier un compteur a l'installation d'un proprietaire de meme nom/prenom (file a valider dans Filament)";

    private const CONFIANCE = 0.5;

    public function handle(): int
    {
        $proprietairesAvecInstallation = DB::table('installation_proprietaire_courante')
            ->join('proprietaire_versions', 'proprietaire_versions.id', '=', 'installation_proprietaire_courante.proprietaire_version_id')
            ->get([
                'installation_proprietaire_courante.installation_id',
                'proprietaire_versions.nom',
                'proprietaire_versions.prenom',
            ])
            ->map(fn ($ligne) => [
                'installation_id' => $ligne->installation_id,
                'nom_tokens' => CorrespondanceNom::tokens($ligne->nom),
                'prenom_tokens' => CorrespondanceNom::tokens($ligne->prenom ?? ''),
            ]);

        $this->info("{$proprietairesAvecInstallation->count()} liens proprietaire-installation connus.");

        $compteurs = CompteurVersion::query()
            ->whereIn('id', function ($sub) {
                $sub->selectRaw('MAX(id)')->from('compteur_versions')->groupBy('numero_compteur');
            })
            ->get(['numero_compteur', 'abonne_nom_brut']);

        $this->info("{$compteurs->count()} compteurs a examiner.");

        $creees = 0;
        $dejaLiees = 0;
        $dejaProposees = 0;
        $sansCandidat = 0;

        $bar = $this->output->createProgressBar($compteurs->count());
        $bar->start();

        foreach ($compteurs as $compteur) {
            $bar->advance();

            $tokensAbonne = CorrespondanceNom::tokens($compteur->abonne_nom_brut ?? '');

            $candidatTrouve = false;

            foreach ($proprietairesAvecInstallation as $proprietaire) {
                $restants = CorrespondanceNom::sansSousSequence($tokensAbonne, $proprietaire['nom_tokens']);

                if ($restants === null || $restants === [] || $proprietaire['prenom_tokens'] === []) {
                    continue;
                }

                if (array_intersect($restants, $proprietaire['prenom_tokens']) === []) {
                    continue;
                }

                $candidatTrouve = true;

                $dejaLie = InstallationCompteurCourant::where('installation_id', $proprietaire['installation_id'])
                    ->where('numero_compteur', $compteur->numero_compteur)
                    ->exists();

                if ($dejaLie) {
                    $dejaLiees++;

                    continue;
                }

                $dejaPropose = RapprochementPropose::where('installation_id', $proprietaire['installation_id'])
                    ->where('type_cible', 'compteur')
                    ->where('cible_id', $compteur->numero_compteur)
                    ->exists();

                if ($dejaPropose) {
                    $dejaProposees++;

                    continue;
                }

                RapprochementPropose::create([
                    'installation_id' => $proprietaire['installation_id'],
                    'type_cible' => 'compteur',
                    'cible_id' => $compteur->numero_compteur,
                    'methode' => 'nom_prenom',
                    'confiance' => self::CONFIANCE,
                ]);
                $creees++;
            }

            if (! $candidatTrouve) {
                $sansCandidat++;
            }
        }

        $bar->finish();
        $this->newLine();

        $this->info("Termine : {$creees} nouvelles propositions, {$dejaLiees} deja liees, {$dejaProposees} deja proposees, {$sansCandidat} compteurs sans candidat nom/prenom.");

        return self::SUCCESS;
    }
}
