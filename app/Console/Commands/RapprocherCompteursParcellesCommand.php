<?php

namespace App\Console\Commands;

use App\Models\AdresseVersion;
use App\Models\CompteurParcelleRapprochement;
use App\Models\CompteurVersion;
use App\Models\ImportBatch;
use App\Support\Adressage;
use Illuminate\Console\Command;

/**
 * Même principe que proprietaires:rapprocher-parcelles, mais l'adresse
 * SOGEDO n'a pas de champ pré-géocodé équivalent à "Points Adresses - base
 * locale" : la commune est un nom libre ("Commune Abonne"), pas un code
 * INSEE. Résolue via les communes déjà connues de adresse_versions (BAN,
 * filtrée au périmètre EPCI), donc fiable sans base externe.
 */
class RapprocherCompteursParcellesCommand extends Command
{
    protected $signature = 'compteurs:rapprocher-parcelles';

    protected $description = "Rapproche chaque compteur de sa/ses parcelle(s) via l'adresse abonne (cad_parcelles de la BAN), sans geocodage";

    public function handle(): int
    {
        $batch = ImportBatch::create([
            'source' => 'rapprochement_adresse_compteur',
            'statut' => 'en_cours',
            'demarre_le' => now(),
        ]);

        $communeParNomNormalise = AdresseVersion::query()
            ->select('code_insee', 'nom_commune')
            ->distinct()
            ->get()
            ->mapWithKeys(fn ($c) => [Adressage::normaliser($c->nom_commune) => $c->code_insee]);

        $compteurs = CompteurVersion::query()
            ->whereIn('id', function ($sub) {
                $sub->selectRaw('MAX(id)')->from('compteur_versions')->groupBy('numero_compteur');
            })
            ->get(['numero_compteur', 'proprietes_brutes']);

        $this->info("{$compteurs->count()} compteurs (dernieres versions) a traiter.");

        $exactes = 0;
        $partielles = 0;
        $sansMatch = 0;
        $sansParcelle = 0;
        $communeInconnue = 0;

        $bar = $this->output->createProgressBar($compteurs->count());
        $bar->start();

        foreach ($compteurs as $compteur) {
            $bar->advance();

            $adresseAbonne = trim($compteur->proprietes_brutes['Adresse Abonne'] ?? '');
            $communeAbonne = trim($compteur->proprietes_brutes['Commune Abonne'] ?? '');

            if ($adresseAbonne === '' || $communeAbonne === '') {
                $sansMatch++;

                continue;
            }

            $codeInsee = $communeParNomNormalise[Adressage::normaliser($communeAbonne)] ?? null;

            if (! $codeInsee) {
                $communeInconnue++;

                continue;
            }

            ['numero' => $numero, 'voie' => $voie] = Adressage::extraireNumeroVoie($adresseAbonne);
            $voieNormalisee = Adressage::normaliser($voie);

            $candidat = AdresseVersion::query()
                ->where('code_insee', $codeInsee)
                ->where('nom_voie_normalise', $voieNormalisee)
                ->where('numero', $numero)
                ->first();

            $methode = 'adresse_exacte';
            $confiance = 1.0;

            if (! $candidat) {
                $candidat = AdresseVersion::query()
                    ->where('code_insee', $codeInsee)
                    ->where('nom_voie_normalise', $voieNormalisee)
                    ->first();
                $methode = 'voie_sans_numero';
                $confiance = 0.5;
            }

            if (! $candidat) {
                $sansMatch++;

                continue;
            }

            if (! $candidat->cad_parcelles) {
                $sansParcelle++;

                continue;
            }

            foreach ($candidat->cad_parcelles as $parcelleId) {
                CompteurParcelleRapprochement::create([
                    'numero_compteur' => $compteur->numero_compteur,
                    'parcelle_id' => $parcelleId,
                    'adresse_version_id' => $candidat->id,
                    'methode' => $methode,
                    'confiance' => $confiance,
                    'import_batch_id' => $batch->id,
                ]);
            }

            $methode === 'adresse_exacte' ? $exactes++ : $partielles++;
        }

        $bar->finish();
        $this->newLine();

        $batch->update([
            'statut' => 'termine',
            'nombre_lignes' => $exactes + $partielles,
            'termine_le' => now(),
            'commentaire' => "{$exactes} exactes, {$partielles} partielles, {$sansParcelle} sans parcelle BAN, {$sansMatch} sans correspondance, {$communeInconnue} commune inconnue",
        ]);

        $this->info("Termine : {$exactes} exactes, {$partielles} partielles (voie seule), {$sansParcelle} adresse trouvee mais sans parcelle liee, {$sansMatch} sans correspondance, {$communeInconnue} commune non reconnue.");

        return self::SUCCESS;
    }
}
