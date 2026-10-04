<?php

namespace App\Console\Commands;

use App\Models\InstallationBatimentEvenement;
use App\Models\InstallationCompteurCourant;
use App\Models\InstallationCompteurEvenement;
use App\Models\InstallationParcelleEvenement;
use App\Models\InstallationProprietaireCourante;
use App\Models\InstallationProprietaireEvenement;
use App\Support\RapprochementAnnexe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rattrapage ponctuel pour les installations-annexes creees avant que
 * installations:bootstrap-depuis-batiments ne les rattache directement a
 * leur maison (voir ce fichier pour le raisonnement). Une installation
 * "annexe" est ici une installation dont le seul batiment courant est de
 * type 02 : son batiment, sa parcelle, ses compteurs et ses proprietaires
 * eventuels sont delies puis relies sur la maison la plus proche de la
 * meme parcelle, via de nouveaux evenements (rien n'est modifie ni
 * supprime : l'ancre "installation" reste en base, immuabilite oblige,
 * mais ne porte plus aucun lien courant une fois le rattachement fait).
 * Une annexe sans aucune maison sur sa parcelle n'a nulle part ou aller :
 * elle est laissee telle quelle.
 */
class FusionnerAnnexesVersMaisonCommand extends Command
{
    protected $signature = 'installations:fusionner-annexes-vers-maison {--dry-run : Affiche le resultat sans ecrire les evenements}';

    protected $description = 'Rattache les installations-annexes existantes (batiment type 02 sans maison) a la maison la plus proche sur la meme parcelle';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $maisonsParParcelle = $this->maisonsParParcelle();

        $installationsAvecMaison = DB::table('installation_batiment_courant as ibc')
            ->join('batiment_versions as bv', 'bv.id', '=', 'ibc.batiment_version_id')
            ->where('bv.type', '01')
            ->pluck('ibc.installation_id')
            ->flip();

        $annexes = DB::table('installation_batiment_courant as ibc')
            ->join('batiment_versions as bv', 'bv.id', '=', 'ibc.batiment_version_id')
            ->where('bv.type', '02')
            ->select('ibc.installation_id', 'ibc.batiment_version_id', 'bv.centroide_lon', 'bv.centroide_lat')
            ->get()
            ->filter(fn ($row) => ! $installationsAvecMaison->has($row->installation_id));

        $this->info("{$annexes->count()} installations-annexes a examiner.");

        $parcelleParInstallation = DB::table('installation_parcelle_courante')
            ->whereIn('installation_id', $annexes->pluck('installation_id'))
            ->pluck('parcelle_id', 'installation_id');

        $fusionnees = 0;
        $orphelines = 0;
        $compteursDeplaces = 0;
        $proprietairesDeplaces = 0;

        $bar = $this->output->createProgressBar($annexes->count());
        $bar->start();

        DB::transaction(function () use (
            $annexes, $parcelleParInstallation, $maisonsParParcelle, $dryRun, $bar,
            &$fusionnees, &$orphelines, &$compteursDeplaces, &$proprietairesDeplaces
        ) {
            foreach ($annexes as $annexe) {
                $bar->advance();

                $parcelleId = $parcelleParInstallation[$annexe->installation_id] ?? null;
                $candidats = $parcelleId ? ($maisonsParParcelle[$parcelleId] ?? []) : [];
                $cible = RapprochementAnnexe::plusProche($candidats, $annexe->centroide_lon, $annexe->centroide_lat);

                if (! $cible) {
                    $orphelines++;

                    continue;
                }

                $fusionnees++;

                if ($dryRun) {
                    continue;
                }

                $this->deplacerBatiment($annexe->installation_id, $cible, $annexe->batiment_version_id);
                $this->deplacerParcelle($annexe->installation_id, $parcelleId);

                $compteursDeplaces += $this->deplacerCompteurs($annexe->installation_id, $cible);
                $proprietairesDeplaces += $this->deplacerProprietaires($annexe->installation_id, $cible);
            }
        });

        $bar->finish();
        $this->newLine();

        $prefixe = $dryRun ? '[dry-run] ' : '';
        $this->info(
            "{$prefixe}Termine : {$fusionnees} annexes rattachees a une maison existante"
            ." ({$compteursDeplaces} compteurs et {$proprietairesDeplaces} proprietaires deplaces), {$orphelines} laissees telles quelles (aucune maison sur leur parcelle)."
        );

        return self::SUCCESS;
    }

    /**
     * parcelle_id => [installation_id => [centroide_lon, centroide_lat]] des
     * installations dont le batiment courant est une maison (type 01).
     *
     * @return array<string, array<string, array{0: float, 1: float}>>
     */
    private function maisonsParParcelle(): array
    {
        return DB::table('installation_parcelle_courante as ipc')
            ->join('installation_batiment_courant as ibc', 'ibc.installation_id', '=', 'ipc.installation_id')
            ->join('batiment_versions as bv', 'bv.id', '=', 'ibc.batiment_version_id')
            ->where('bv.type', '01')
            ->select('ipc.parcelle_id', 'ipc.installation_id', 'bv.centroide_lon', 'bv.centroide_lat')
            ->get()
            ->groupBy('parcelle_id')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($r) => [$r->installation_id => [$r->centroide_lon, $r->centroide_lat]])->all())
            ->all();
    }

    private function deplacerBatiment(string $source, string $cible, int $batimentVersionId): void
    {
        $motif = 'Fusion automatique annexe -> maison la plus proche sur la meme parcelle';

        InstallationBatimentEvenement::create([
            'installation_id' => $source,
            'batiment_version_id' => $batimentVersionId,
            'action' => 'delier',
            'source' => 'auto',
            'motif' => $motif,
        ]);

        InstallationBatimentEvenement::create([
            'installation_id' => $cible,
            'batiment_version_id' => $batimentVersionId,
            'action' => 'lier',
            'source' => 'auto',
            'motif' => $motif,
        ]);
    }

    private function deplacerParcelle(string $source, ?string $parcelleId): void
    {
        if (! $parcelleId) {
            return;
        }

        InstallationParcelleEvenement::create([
            'installation_id' => $source,
            'parcelle_id' => $parcelleId,
            'action' => 'delier',
            'source' => 'auto',
            'motif' => 'Fusion automatique annexe -> maison la plus proche sur la meme parcelle',
        ]);
    }

    private function deplacerCompteurs(string $source, string $cible): int
    {
        $compteurs = InstallationCompteurCourant::where('installation_id', $source)->get();

        foreach ($compteurs as $compteur) {
            $motif = 'Fusion automatique annexe -> maison la plus proche sur la meme parcelle';

            InstallationCompteurEvenement::create([
                'installation_id' => $source,
                'numero_compteur' => $compteur->numero_compteur,
                'action' => 'delier',
                'source' => 'auto',
                'motif' => $motif,
            ]);

            InstallationCompteurEvenement::create([
                'installation_id' => $cible,
                'numero_compteur' => $compteur->numero_compteur,
                'action' => 'lier',
                'source' => 'auto',
                'motif' => $motif,
            ]);
        }

        return $compteurs->count();
    }

    private function deplacerProprietaires(string $source, string $cible): int
    {
        $proprietaires = InstallationProprietaireCourante::where('installation_id', $source)->get();

        foreach ($proprietaires as $proprietaire) {
            $motif = 'Fusion automatique annexe -> maison la plus proche sur la meme parcelle';

            InstallationProprietaireEvenement::create([
                'installation_id' => $source,
                'proprietaire_version_id' => $proprietaire->proprietaire_version_id,
                'action' => 'delier',
                'source' => 'auto',
                'motif' => $motif,
            ]);

            InstallationProprietaireEvenement::create([
                'installation_id' => $cible,
                'proprietaire_version_id' => $proprietaire->proprietaire_version_id,
                'action' => 'lier',
                'source' => 'auto',
                'motif' => $motif,
            ]);
        }

        return $proprietaires->count();
    }
}
