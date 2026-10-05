<?php

namespace App\Http\Controllers;

use App\Models\AdresseVersion;
use App\Models\Installation;
use App\Models\ParcelleVersion;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export XLSX synchrone (pas de file d'attente) : NativePHP n'a pas
 * forcément de worker de queue qui tourne en permanence, un export en
 * attente d'un job qui ne se lance jamais serait pire qu'un telechargement
 * un peu plus long.
 */
class ExportController extends Controller
{
    private const ENTETES = [
        'Installation', 'Propriétaire(s)', 'Adresse', 'Parcelle(s)', 'Commune',
        'Type', 'Statut', 'Dernier rapport', 'État du rapport',
    ];

    public function page(): InertiaResponse
    {
        return Inertia::render('export');
    }

    public function installationsXlsx(): StreamedResponse
    {
        ini_set('memory_limit', '2048M');

        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $contexte = $this->creerFeuillesParCommune($spreadsheet);
        $feuilles = $contexte['feuilles'];
        $lignes = $contexte['lignes'];

        Installation::query()
            ->with([
                'etatCourant',
                'rapportCourant',
                'parcellesCourantes',
                'proprietairesCourants.proprietaireVersion',
            ])
            ->orderBy('id')
            ->chunkById(200, function ($installations) use ($feuilles, &$lignes) {
                $this->ecrireChunk($installations, $feuilles, $lignes);
            });

        $writer = new Xlsx($spreadsheet);
        $nomFichier = 'installations_recapitulatif_'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $nomFichier, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Une feuille par commune (ordre alphabétique, même liste que les
     * filtres du dashboard) + une feuille "Sans commune" en repli pour les
     * installations sans parcelle ou dont la commune n'a pas pu être
     * résolue. Créées à l'avance pour un ordre prévisible, pas dans
     * l'ordre de rencontre pendant le chunk.
     *
     * @return array{feuilles: array<string, Worksheet>, lignes: array<string, int>}
     */
    private function creerFeuillesParCommune(Spreadsheet $spreadsheet): array
    {
        $communes = AdresseVersion::query()
            ->whereNotNull('nom_commune')
            ->distinct()
            ->orderBy('nom_commune')
            ->pluck('nom_commune');

        $feuilles = [];
        $lignes = [];

        foreach ([...$communes, 'Sans commune'] as $nomCommune) {
            $titre = mb_substr(preg_replace('/[:\\\\\/?*\[\]]/', ' ', $nomCommune) ?? $nomCommune, 0, 31);
            $feuille = $spreadsheet->createSheet();
            $feuille->setTitle($titre);
            $feuille->fromArray(self::ENTETES, null, 'A1');
            $feuille->getStyle('A1:I1')->getFont()->setBold(true);

            $feuilles[$nomCommune] = $feuille;
            $lignes[$nomCommune] = 2;
        }

        return ['feuilles' => $feuilles, 'lignes' => $lignes];
    }

    /**
     * @param  Collection<int, Installation>  $installations
     * @param  array<string, Worksheet>  $feuilles
     * @param  array<string, int>  $lignes
     */
    private function ecrireChunk($installations, array $feuilles, array &$lignes): void
    {
        $parcelleIds = $installations->flatMap(fn (Installation $i) => $i->parcellesCourantes->pluck('parcelle_id'))->unique()->values();

        $parcelleInfo = ParcelleVersion::query()
            ->whereIn('parcelle_id', $parcelleIds)
            ->orderByDesc('id')
            ->get(['parcelle_id', 'section', 'numero', 'commune_insee'])
            ->unique('parcelle_id')
            ->keyBy('parcelle_id');

        $communeParInsee = AdresseVersion::query()
            ->whereNotNull('nom_commune')
            ->distinct()
            ->pluck('nom_commune', 'code_insee');

        $adresses = AdresseVersion::query()
            ->where(function ($query) use ($parcelleIds) {
                foreach ($parcelleIds as $parcelleId) {
                    $query->orWhereJsonContains('cad_parcelles', $parcelleId);
                }
            })
            ->get();

        $adresseParParcelle = [];
        foreach ($adresses as $adresse) {
            foreach (($adresse->cad_parcelles ?? []) as $parcelleId) {
                $adresseParParcelle[$parcelleId][] = trim("{$adresse->numero} {$adresse->nom_voie}").", {$adresse->code_postal} {$adresse->nom_commune}";
            }
        }

        foreach ($installations as $installation) {
            $parcelleIdsInstallation = $installation->parcellesCourantes->pluck('parcelle_id');

            $proprietaires = $installation->proprietairesCourants
                ->map(fn ($lien) => trim("{$lien->proprietaireVersion?->nom} {$lien->proprietaireVersion?->prenom}"))
                ->filter()
                ->unique()
                ->implode(', ');

            $adresse = $parcelleIdsInstallation
                ->flatMap(fn ($id) => $adresseParParcelle[$id] ?? [])
                ->unique()
                ->implode(' | ');

            $parcelles = $parcelleIdsInstallation
                ->map(fn ($id) => isset($parcelleInfo[$id]) ? "{$parcelleInfo[$id]->section}{$parcelleInfo[$id]->numero}" : $id)
                ->implode(', ');

            $commune = $parcelleIdsInstallation->isNotEmpty() && isset($parcelleInfo[$parcelleIdsInstallation->first()])
                ? ($communeParInsee[$parcelleInfo[$parcelleIdsInstallation->first()]->commune_insee] ?? null)
                : null;

            $nomFeuille = $commune && isset($feuilles[$commune]) ? $commune : 'Sans commune';
            $feuille = $feuilles[$nomFeuille];
            $ligne = $lignes[$nomFeuille];

            $feuille->fromArray([
                $installation->id,
                $proprietaires ?: 'Propriétaire inconnu',
                $adresse ?: '',
                $parcelles ?: '',
                $commune ?: '',
                $installation->etatCourant?->type ?? '',
                $installation->etatCourant?->statut ?? '',
                $installation->rapportCourant?->date_controle?->format('d/m/Y') ?? '',
                $this->libelleConclusion($installation->rapportCourant?->conclusion),
            ], null, "A{$ligne}");

            $lignes[$nomFeuille]++;
        }
    }

    private function libelleConclusion(?string $conclusion): string
    {
        return match ($conclusion) {
            'conforme' => 'Conforme',
            'non_conforme' => 'Non conforme',
            'avec_reserves' => 'Avec réserves',
            null => 'Aucun rapport',
            default => 'Autre / non renseigné',
        };
    }
}
