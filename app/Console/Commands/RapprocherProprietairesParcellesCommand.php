<?php

namespace App\Console\Commands;

use App\Models\AdresseVersion;
use App\Models\ImportBatch;
use App\Models\ProprietaireParcelleRapprochement;
use App\Models\ProprietaireVersion;
use App\Support\Adressage;
use Illuminate\Console\Command;

class RapprocherProprietairesParcellesCommand extends Command
{
    protected $signature = 'proprietaires:rapprocher-parcelles';

    protected $description = "Rapproche chaque proprietaire de sa/ses parcelle(s) via l'adresse (cad_parcelles de la BAN), sans geocodage";

    public function handle(): int
    {
        $batch = ImportBatch::create([
            'source' => 'rapprochement_adresse',
            'statut' => 'en_cours',
            'demarre_le' => now(),
        ]);

        $proprietaires = ProprietaireVersion::query()
            ->whereNotNull('proprietes_brutes')
            ->get(['id', 'proprietes_brutes']);

        $this->info("{$proprietaires->count()} proprietaires avec une adresse source a traiter.");

        $exactes = 0;
        $partielles = 0;
        $sansMatch = 0;
        $sansParcelle = 0;

        $bar = $this->output->createProgressBar($proprietaires->count());
        $bar->start();

        foreach ($proprietaires as $proprietaire) {
            $bar->advance();

            $adresseBrute = $proprietaire->proprietes_brutes['Points Adresses - base locale'] ?? null;
            $codeInsee = $proprietaire->proprietes_brutes['Code insee'] ?? null;

            if (! $adresseBrute || ! $codeInsee) {
                $sansMatch++;

                continue;
            }

            ['numero' => $numero, 'voie' => $voie] = Adressage::extraireNumeroVoie($adresseBrute);
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
                ProprietaireParcelleRapprochement::create([
                    'proprietaire_version_id' => $proprietaire->id,
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
            'commentaire' => "{$exactes} exactes, {$partielles} partielles, {$sansParcelle} sans parcelle BAN, {$sansMatch} sans correspondance",
        ]);

        $this->info("Termine : {$exactes} exactes, {$partielles} partielles (voie seule), {$sansParcelle} adresse trouvee mais sans parcelle liee, {$sansMatch} sans correspondance.");

        return self::SUCCESS;
    }
}
