<?php

namespace App\Console\Commands;

use App\Models\AdresseVersion;
use App\Models\CompteurVersion;
use App\Models\InstallationCompteurCourant;
use App\Models\InstallationParcelleCourante;
use App\Models\InstallationProprietaireCourante;
use App\Models\LogementHorsAssCollVersion;
use App\Models\ParcelleVersion;
use App\Models\RapprochementPropose;
use App\Support\CorrespondanceNom;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Complément à compteurs:rapprocher-parcelles (texte de voie BAN) : les
 * lieux-dits ruraux ("LIEU-DIT GRATECAP" côté SOGEDO) ne matchent jamais une
 * voie BAN ("Route de Gratecap" — aucun mot commun). Le fichier logement
 * hors assainissement collectif, lui, nomme la même zone exactement comme
 * SOGEDO ("GRATECAP", son "Adresse parcelle (cadastre)") : utilisé ici comme
 * pont.
 *
 * Un lieu-dit couvre plusieurs parcelles (6 pour Gratecap dans les données
 * réelles) : pas assez précis seul pour désigner une installation. Désambigüisé
 * en croisant avec le propriétaire déjà lié à chaque installation candidate :
 * si un seul correspond au nom de l'abonné du compteur, confiance plus
 * élevée ; sinon toutes les installations du lieu-dit sont proposées à
 * confiance réduite, à trancher humainement.
 */
class ProposerCompteursParLieuDitCommand extends Command
{
    private const CONFIANCE_NOM_CONFIRME = 0.8;

    private const CONFIANCE_SANS_CONFIRMATION = 0.35;

    protected $signature = 'installations:proposer-compteurs-par-lieu-dit';

    protected $description = "Propose de lier un compteur a une installation via le lieu-dit (fichier logement hors assainissement collectif), quand l'adresse BAN n'a rien trouve";

    public function handle(): int
    {
        $communeParNomNormalise = AdresseVersion::query()
            ->select('code_insee', 'nom_commune')
            ->distinct()
            ->get()
            ->mapWithKeys(fn ($c) => [CorrespondanceNom::tokens($c->nom_commune) === [] ? '' : implode(' ', CorrespondanceNom::tokens($c->nom_commune)) => $c->code_insee]);

        $logementsParCommune = $this->logementsParCommune();

        $compteurs = CompteurVersion::query()
            ->whereIn('id', function ($sub) {
                $sub->selectRaw('MAX(id)')->from('compteur_versions')->groupBy('numero_compteur');
            })
            ->get(['numero_compteur', 'abonne_nom_brut', 'proprietes_brutes']);

        $this->info("{$compteurs->count()} compteurs a examiner.");

        $creees = 0;
        $dejaLiees = 0;
        $dejaProposees = 0;
        $sansLieuDit = 0;
        $communeInconnue = 0;
        $sansCandidat = 0;

        $bar = $this->output->createProgressBar($compteurs->count());
        $bar->start();

        foreach ($compteurs as $compteur) {
            $bar->advance();

            $adresseAbonne = trim($compteur->proprietes_brutes['Adresse Abonne'] ?? '');
            $communeAbonne = trim($compteur->proprietes_brutes['Commune Abonne'] ?? '');

            $nomLieu = preg_replace('/^LIEU[\s-]?DIT\s*/i', '', $adresseAbonne);

            if ($nomLieu === $adresseAbonne || trim((string) $nomLieu) === '') {
                $sansLieuDit++;

                continue;
            }

            $codeInsee = $communeParNomNormalise[implode(' ', CorrespondanceNom::tokens($communeAbonne))] ?? null;

            if (! $codeInsee) {
                $communeInconnue++;

                continue;
            }

            $tokensLieuDit = CorrespondanceNom::tokens($nomLieu);

            if ($tokensLieuDit === []) {
                $sansLieuDit++;

                continue;
            }

            $candidats = ($logementsParCommune[$codeInsee] ?? collect())
                ->filter(fn ($l) => array_diff($tokensLieuDit, $l['tokens_adresse']) === []);

            if ($candidats->isEmpty()) {
                $sansCandidat++;

                continue;
            }

            $installationIds = $candidats->pluck('parcelle_id')->unique()->flatMap(
                fn ($parcelleId) => InstallationParcelleCourante::where('parcelle_id', $parcelleId)->pluck('installation_id')
            )->unique();

            if ($installationIds->isEmpty()) {
                $sansCandidat++;

                continue;
            }

            $confirmeParInstallation = $installationIds->mapWithKeys(fn ($installationId) => [
                $installationId => InstallationProprietaireCourante::where('installation_id', $installationId)
                    ->with('proprietaireVersion')
                    ->get()
                    ->contains(fn ($lien) => $lien->proprietaireVersion && CorrespondanceNom::correspond(
                        $compteur->abonne_nom_brut ?? '',
                        $lien->proprietaireVersion->nom,
                        $lien->proprietaireVersion->prenom ?? ''
                    )),
            ]);

            // Si le nom confirme au moins une installation du lieu-dit, les
            // autres (non confirmees) n'apportent que du bruit a la revue :
            // elles sont ecartees plutot que proposees a confiance reduite.
            $auMoinsUneConfirmee = $confirmeParInstallation->contains(true);

            foreach ($installationIds as $installationId) {
                $nomConfirme = $confirmeParInstallation[$installationId];

                if ($auMoinsUneConfirmee && ! $nomConfirme) {
                    continue;
                }

                $dejaLie = InstallationCompteurCourant::where('installation_id', $installationId)
                    ->where('numero_compteur', $compteur->numero_compteur)
                    ->exists();

                if ($dejaLie) {
                    $dejaLiees++;

                    continue;
                }

                $dejaPropose = RapprochementPropose::where('installation_id', $installationId)
                    ->where('type_cible', 'compteur')
                    ->where('cible_id', $compteur->numero_compteur)
                    ->exists();

                if ($dejaPropose) {
                    $dejaProposees++;

                    continue;
                }

                RapprochementPropose::create([
                    'installation_id' => $installationId,
                    'type_cible' => 'compteur',
                    'cible_id' => $compteur->numero_compteur,
                    'methode' => $nomConfirme ? 'lieu_dit_logement_nom_confirme' : 'lieu_dit_logement',
                    'confiance' => $nomConfirme ? self::CONFIANCE_NOM_CONFIRME : self::CONFIANCE_SANS_CONFIRMATION,
                ]);
                $creees++;
            }
        }

        $bar->finish();
        $this->newLine();

        $this->info("Termine : {$creees} nouvelles propositions, {$dejaLiees} deja liees, {$dejaProposees} deja proposees, {$sansLieuDit} sans lieu-dit, {$communeInconnue} commune non reconnue, {$sansCandidat} sans candidat.");

        return self::SUCCESS;
    }

    /**
     * Logements groupes par commune (via la parcelle), avec les tokens de
     * leur adresse cadastrale pre-calcules.
     *
     * @return Collection<string, Collection<int, array{parcelle_id: string, tokens_adresse: non-empty-array<int, string>}>>
     */
    private function logementsParCommune(): Collection
    {
        $communeParParcelle = ParcelleVersion::pluck('commune_insee', 'parcelle_id');

        return LogementHorsAssCollVersion::all(['parcelle_id', 'proprietes_brutes'])
            ->map(fn ($l) => [
                'parcelle_id' => (string) $l->parcelle_id,
                'commune_insee' => $communeParParcelle[$l->parcelle_id] ?? null,
                'tokens_adresse' => CorrespondanceNom::tokens($l->proprietes_brutes['Adresse parcelle (cadastre)'] ?? ''),
            ])
            ->filter(fn ($l) => $l['commune_insee'] !== null && $l['tokens_adresse'] !== [])
            ->groupBy('commune_insee')
            ->map(fn (Collection $groupe) => $groupe->map(fn (array $l) => [
                'parcelle_id' => $l['parcelle_id'],
                'tokens_adresse' => $l['tokens_adresse'],
            ])->values())
            ->mapWithKeys(fn (Collection $groupe, $codeInsee) => [(string) $codeInsee => $groupe]);
    }
}
