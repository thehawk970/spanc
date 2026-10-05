<?php

namespace App\Http\Controllers;

use App\Models\AdresseVersion;
use App\Models\CompteurVersion;
use App\Models\Installation;
use App\Models\ParcelleVersion;
use App\Models\RapprochementPropose;
use App\Support\CorrespondanceNom;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
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
    /** Colonnes toujours visibles. */
    private const ENTETES_BASE = [
        'Installation', 'Propriétaire(s)', 'Adresse', 'Parcelle(s)', 'Commune',
        'Type', 'Statut', 'Dernier rapport', 'État du rapport',
    ];

    /**
     * Colonnes repliées par défaut (groupe Excel, bouton +/- en haut de
     * colonne) : detail + recoupements, pour verifier ligne par ligne
     * d'où vient chaque donnée sans alourdir la vue par défaut.
     */
    private const ENTETES_DETAIL = [
        'Code INSEE', 'Bâtiment(s)', 'Compteurs', 'Statut occupation',
        'Nb parcelles', 'Nb bâtiments', 'Nb compteurs', 'Nb propriétaires', 'Créée le',
        'Parcelle(s) — rapprochement', 'Propriétaire(s) — rapprochement', 'Compteur(s) — rapprochement',
        'Propositions en attente',
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
                'parcellesCourantes.evenement',
                'compteursCourants.evenement',
                'batimentsCourants.batimentVersion',
                'proprietairesCourants.proprietaireVersion',
                'proprietairesCourants.evenement',
            ])
            ->withCount(['parcellesCourantes', 'batimentsCourants', 'compteursCourants', 'proprietairesCourants'])
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
     * l'ordre de rencontre pendant le chunk. Les colonnes de détail sont
     * groupées et repliées (bouton +/- Excel) dès la création.
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

        $entetes = [...self::ENTETES_BASE, ...self::ENTETES_DETAIL];
        $premiereColDetail = count(self::ENTETES_BASE) + 1;
        $derniereCol = count($entetes);

        $feuilles = [];
        $lignes = [];

        foreach ([...$communes, 'Sans commune'] as $nomCommune) {
            $titre = mb_substr(preg_replace('/[:\\\\\/?*\[\]]/', ' ', $nomCommune) ?? $nomCommune, 0, 31);
            $feuille = $spreadsheet->createSheet();
            $feuille->setTitle($titre);
            $feuille->fromArray($entetes, null, 'A1');
            $feuille->getStyle('A1:'.Coordinate::stringFromColumnIndex($derniereCol).'1')->getFont()->setBold(true);

            $feuille->setShowSummaryRight(true);
            for ($col = $premiereColDetail; $col <= $derniereCol; $col++) {
                $lettre = Coordinate::stringFromColumnIndex($col);
                $feuille->getColumnDimension($lettre)->setOutlineLevel(1);
                $feuille->getColumnDimension($lettre)->setVisible(false);
            }
            // Le bouton +/- se pose sur la colonne juste apres le groupe (resume a
            // droite) : ici la derniere colonne du groupe, puisque rien ne suit.
            $feuille->getColumnDimension(Coordinate::stringFromColumnIndex($derniereCol))->setCollapsed(true);

            $feuilles[$nomCommune] = $feuille;
            $lignes[$nomCommune] = 2;
        }

        return ['feuilles' => $feuilles, 'lignes' => $lignes];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Installation>  $installations
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

        $numerosCompteur = $installations->flatMap(fn (Installation $i) => $i->compteursCourants->pluck('numero_compteur'))->unique()->values();

        $abonneParCompteur = CompteurVersion::query()
            ->whereIn('numero_compteur', $numerosCompteur)
            ->whereIn('id', function ($sub) {
                $sub->selectRaw('MAX(id)')->from('compteur_versions')->groupBy('numero_compteur');
            })
            ->pluck('abonne_nom_brut', 'numero_compteur');

        $installationIds = $installations->pluck('id');

        $propositionsEnAttente = RapprochementPropose::whereIn('installation_id', $installationIds)
            ->whereDoesntHave('decisions')
            ->get()
            ->groupBy('installation_id');

        foreach ($installations as $installation) {
            $this->ecrireLigne($installation, $feuilles, $lignes, $parcelleInfo, $communeParInsee, $adresseParParcelle, $abonneParCompteur, $propositionsEnAttente);
        }
    }

    /**
     * @param  array<string, Worksheet>  $feuilles
     * @param  array<string, int>  $lignes
     * @param  Collection<string, ParcelleVersion>  $parcelleInfo
     * @param  Collection<string, string>  $communeParInsee
     * @param  array<string, array<int, string>>  $adresseParParcelle
     * @param  Collection<string, ?string>  $abonneParCompteur
     * @param  Collection<int|string, Collection<int, RapprochementPropose>>  $propositionsEnAttente
     */
    private function ecrireLigne(
        Installation $installation,
        array $feuilles,
        array &$lignes,
        Collection $parcelleInfo,
        Collection $communeParInsee,
        array $adresseParParcelle,
        Collection $abonneParCompteur,
        Collection $propositionsEnAttente,
    ): void {
        $parcelleIdsInstallation = $installation->parcellesCourantes->pluck('parcelle_id');

        $proprietairesVersions = $installation->proprietairesCourants->map(fn ($lien) => $lien->proprietaireVersion)->filter();
        $proprietaires = $proprietairesVersions->map(fn ($p) => trim("{$p->nom} {$p->prenom}"))->unique()->implode(', ');

        $adresse = $parcelleIdsInstallation->flatMap(fn ($id) => $adresseParParcelle[$id] ?? [])->unique()->implode(' | ');

        $parcelles = $parcelleIdsInstallation
            ->map(fn ($id) => isset($parcelleInfo[$id]) ? "{$parcelleInfo[$id]->section}{$parcelleInfo[$id]->numero}" : $id)
            ->implode(', ');

        $commune = $parcelleIdsInstallation->isNotEmpty() && isset($parcelleInfo[$parcelleIdsInstallation->first()])
            ? ($communeParInsee[$parcelleInfo[$parcelleIdsInstallation->first()]->commune_insee] ?? null)
            : null;
        $codeInsee = $parcelleIdsInstallation->isNotEmpty() ? ($parcelleInfo[$parcelleIdsInstallation->first()]->commune_insee ?? null) : null;

        $batiments = $installation->batimentsCourants
            ->map(fn ($lien) => match ($lien->batimentVersion?->type) {
                '01' => 'Maison',
                '02' => 'Annexe',
                default => null,
            })
            ->filter()
            ->implode(', ');

        $compteurs = $installation->compteursCourants->pluck('numero_compteur')->implode(', ');

        $abonnes = $installation->compteursCourants->pluck('numero_compteur')->map(fn ($n) => $abonneParCompteur[$n] ?? null)->filter();
        $statutOccupation = '—';
        if ($abonnes->isNotEmpty() && $proprietairesVersions->isNotEmpty()) {
            $statutOccupation = $abonnes->every(fn ($abonne) => $proprietairesVersions->contains(
                fn ($p) => CorrespondanceNom::correspond($abonne, $p->nom, $p->prenom ?? '')
            )) ? 'Propriétaire occupant' : 'Loué (probable)';
        }

        $rapprochementParcelles = $installation->parcellesCourantes
            ->map(fn ($lien) => "{$lien->parcelle_id} : {$lien->evenement?->source} / ".number_format((float) $lien->evenement?->confiance, 2).' / '.($lien->evenement?->motif ?: '—'))
            ->implode(' | ');

        $rapprochementProprietaires = $installation->proprietairesCourants
            ->map(fn ($lien) => trim("{$lien->proprietaireVersion?->nom} {$lien->proprietaireVersion?->prenom}").' : '.($lien->evenement?->source).' / '.number_format((float) $lien->evenement?->confiance, 2).' / '.($lien->evenement?->motif ?: '—'))
            ->implode(' | ');

        $rapprochementCompteurs = $installation->compteursCourants
            ->map(fn ($lien) => "{$lien->numero_compteur} : {$lien->evenement?->source} / ".number_format((float) $lien->evenement?->confiance, 2).' / '.($lien->evenement?->motif ?: '—'))
            ->implode(' | ');

        $enAttente = $propositionsEnAttente->get($installation->id, collect());
        $resumeEnAttente = $enAttente->isEmpty() ? '' : $enAttente->count().' proposition(s) : '.$enAttente
            ->map(fn ($p) => "{$p->type_cible} ({$p->methode}, conf. ".number_format((float) $p->confiance, 2).')')
            ->implode(' | ');

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
            $codeInsee ?? '',
            $batiments ?: '',
            $compteurs ?: '',
            $statutOccupation,
            $installation->parcelles_courantes_count,
            $installation->batiments_courants_count,
            $installation->compteurs_courants_count,
            $installation->proprietaires_courants_count,
            $installation->created_at->format('d/m/Y H:i'),
            $rapprochementParcelles,
            $rapprochementProprietaires,
            $rapprochementCompteurs,
            $resumeEnAttente,
        ], null, "A{$ligne}");

        $lignes[$nomFeuille]++;
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
