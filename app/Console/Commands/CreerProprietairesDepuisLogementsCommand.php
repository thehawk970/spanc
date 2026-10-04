<?php

namespace App\Console\Commands;

use App\Models\ImportBatch;
use App\Models\InstallationParcelleCourante;
use App\Models\InstallationProprietaireCourante;
use App\Models\InstallationProprietaireEvenement;
use App\Models\LogementHorsAssCollVersion;
use App\Models\ProprietaireVersion;
use App\Support\Civilite;
use App\Support\CorrespondanceNom;
use Illuminate\Console\Command;

/**
 * Comble le propriétaire manquant du BAN (voir
 * ProposerProprietairesParLogementCommand pour le cas où un propriétaire
 * BAN existe déjà et correspond par nom/prénom). Le référentiel BAN pro
 * n'a jamais couvert que Pays de Belvès : sur les 19 autres communes, sans
 * ce complément, aucun propriétaire n'est trouvable.
 *
 * Contrairement au rapprochement par nom (confiance < 1.0, à valider
 * humainement — on choisit entre plusieurs identités déjà connues, avec un
 * vrai risque de se tromper de personne), ici il n'y a pas d'ambiguïté : on
 * crée une identité qui n'existe encore nulle part et on la lie à
 * l'installation qui porte la même parcelle exacte (idu). Même niveau de
 * confiance que le bootstrap BAN par adresse.
 *
 * Un seul ProprietaireVersion par numero_proprietaire (clé fiscale stable,
 * un même propriétaire possède souvent plusieurs parcelles/logements) :
 * sans ça, un propriétaire de 5 logements se retrouverait dupliqué 5 fois.
 */
class CreerProprietairesDepuisLogementsCommand extends Command
{
    protected $signature = 'installations:creer-proprietaires-depuis-logements';

    protected $description = 'Cree les proprietaires du fichier logement hors assainissement collectif absents du referentiel BAN, et les lie directement a leur(s) installation(s)';

    public function handle(): int
    {
        $proprietairesExistants = ProprietaireVersion::all(['id', 'nom', 'prenom'])
            ->map(fn ($p) => [
                'nom_tokens' => CorrespondanceNom::tokens($p->nom),
                'prenom_tokens' => CorrespondanceNom::tokens($p->prenom ?? ''),
            ]);

        $logements = LogementHorsAssCollVersion::whereNotNull('numero_proprietaire')
            ->get(['parcelle_id', 'numero_proprietaire', 'proprietaire_nom_brut'])
            ->groupBy('numero_proprietaire');

        $this->info("{$logements->count()} proprietaires fiscaux distincts a examiner.");

        $batch = ImportBatch::create([
            'source' => 'logement_hors_ass_coll_proprietaire',
            'statut' => 'en_cours',
            'demarre_le' => now(),
        ]);

        $crees = 0;
        $dejaConnus = 0;
        $lies = 0;
        $dejaLies = 0;
        $sansInstallation = 0;

        $bar = $this->output->createProgressBar($logements->count());
        $bar->start();

        foreach ($logements as $numeroProprietaire => $lignes) {
            $bar->advance();

            $nomBrut = $lignes->pluck('proprietaire_nom_brut')->filter()->first();

            if (! $nomBrut) {
                continue;
            }

            $tokensNomBrut = CorrespondanceNom::tokens($nomBrut);

            $dejaConnu = $proprietairesExistants->contains(function ($p) use ($tokensNomBrut) {
                $restants = CorrespondanceNom::sansSousSequence($tokensNomBrut, $p['nom_tokens']);

                return $restants !== null && $restants !== [] && $p['prenom_tokens'] !== []
                    && array_intersect($restants, $p['prenom_tokens']) !== [];
            });

            if ($dejaConnu) {
                $dejaConnus++;

                continue;
            }

            $installationIds = InstallationParcelleCourante::whereIn('parcelle_id', $lignes->pluck('parcelle_id'))
                ->pluck('installation_id')
                ->unique();

            if ($installationIds->isEmpty()) {
                $sansInstallation++;

                continue;
            }

            [$civilite, $nomPropre] = Civilite::extraire($nomBrut);

            $proprietaire = ProprietaireVersion::create([
                'nom' => $nomPropre,
                'prenom' => null,
                'civilite' => $civilite,
                'contact' => null,
                'proprietes_brutes' => ['numero_proprietaire' => $numeroProprietaire, 'source' => 'logement_hors_ass_coll'],
                'import_batch_id' => $batch->id,
            ]);
            $crees++;

            foreach ($installationIds as $installationId) {
                $dejaLie = InstallationProprietaireCourante::where('installation_id', $installationId)
                    ->where('proprietaire_version_id', $proprietaire->id)
                    ->exists();

                if ($dejaLie) {
                    $dejaLies++;

                    continue;
                }

                InstallationProprietaireEvenement::create([
                    'installation_id' => $installationId,
                    'proprietaire_version_id' => $proprietaire->id,
                    'action' => 'lier',
                    'source' => 'auto',
                    'confiance' => 1.0,
                    'motif' => 'Proprietaire fiscal (logement hors assainissement collectif), parcelle exacte',
                ]);
                $lies++;
            }
        }

        $bar->finish();
        $this->newLine();

        $batch->update([
            'statut' => 'termine',
            'nombre_lignes' => $crees,
            'termine_le' => now(),
        ]);

        $this->info("Termine : {$crees} proprietaires crees, {$lies} liens crees, {$dejaConnus} deja connus du referentiel BAN (laisses au rapprochement par nom), {$dejaLies} deja lies, {$sansInstallation} sans installation sur leur(s) parcelle(s).");

        return self::SUCCESS;
    }
}
