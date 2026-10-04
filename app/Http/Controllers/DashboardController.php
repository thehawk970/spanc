<?php

namespace App\Http\Controllers;

use App\Models\AdresseVersion;
use App\Models\Installation;
use App\Models\ParcelleVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const TYPES = [
        'non_determine' => 'Non déterminé',
        'collectif' => 'Collectif',
        'non_collectif' => 'Non collectif',
    ];

    private const STATUTS = [
        'a_statuer' => 'À statuer',
        'a_controler' => 'À contrôler',
        'actif' => 'Actif',
        'inactif' => 'Inactif',
        'abandonne' => 'Abandonné',
    ];

    /**
     * Codes bruts cadastre.data.gouv.fr (champ `type` des bâtiments) : une
     * installation est créée par bâtiment rapproché, donc maison et annexe
     * d'une même parcelle apparaissent comme deux installations distinctes.
     */
    private const BATIMENT_TYPES = [
        '01' => 'maison',
        '02' => 'annexe',
    ];

    private const OWNER_SORTS = ['nom', 'installations_count'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'proprietaire' => ['nullable', 'string', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'parcelle' => ['nullable', 'string', 'max:255'],
            'compteur' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::TYPES))],
            'statut' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::STATUTS))],
            'commune' => ['nullable', 'string', 'max:5'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', self::OWNER_SORTS)],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = $filters['per_page'] ?? 50;
        $page = $filters['page'] ?? 1;
        $sort = $filters['sort'] ?? 'nom';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $owners = $this->ownersMatching($filters);

        $owners = $sort === 'installations_count'
            ? $owners->sortBy(fn ($o) => $o['installation_ids']->count(), SORT_REGULAR, $direction === 'desc')
            : $owners->sortBy('nom', SORT_NATURAL | SORT_FLAG_CASE, $direction === 'desc');

        $total = $owners->count();
        $pageOwners = $owners->slice(($page - 1) * $perPage, $perPage)->values();

        $installationIds = $pageOwners->flatMap(fn ($o) => $o['installation_ids'])->unique()->values();

        $communesByInsee = AdresseVersion::query()
            ->whereNotNull('nom_commune')
            ->distinct()
            ->orderBy('nom_commune')
            ->pluck('nom_commune', 'code_insee');

        $installationsById = Installation::query()
            ->whereIn('id', $installationIds)
            ->with([
                'etatCourant',
                'parcellesCourantes',
                'compteursCourants',
                'proprietairesCourants.proprietaireVersion',
                'batimentsCourants.batimentVersion',
            ])
            ->withCount(['parcellesCourantes', 'batimentsCourants', 'compteursCourants', 'proprietairesCourants'])
            ->get()
            ->keyBy('id');

        $adresseMap = $this->adresseMapFor($installationsById->values());
        $parcelleInfo = $this->parcelleInfoFor($installationsById->values());

        $pageOwners = $pageOwners->map(function ($owner) use ($installationsById, $adresseMap, $parcelleInfo, $communesByInsee) {
            $installations = $owner['installation_ids']
                ->map(fn ($id) => $installationsById->get($id))
                ->filter()
                ->map(fn (Installation $i) => $this->present($i, $adresseMap, $parcelleInfo, $communesByInsee))
                ->sortBy(['commune', 'adresse'])
                ->values();

            return [
                'nom' => $owner['nom'],
                'contact' => $owner['contact'],
                'installations_count' => $installations->count(),
                'installations' => $installations,
            ];
        });

        $paginator = new LengthAwarePaginator($pageOwners, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        return Inertia::render('dashboard', [
            'proprietaires' => $paginator,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'proprietaire' => $filters['proprietaire'] ?? '',
                'adresse' => $filters['adresse'] ?? '',
                'parcelle' => $filters['parcelle'] ?? '',
                'compteur' => $filters['compteur'] ?? '',
                'type' => $filters['type'] ?? '',
                'statut' => $filters['statut'] ?? '',
                'commune' => $filters['commune'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'filterOptions' => [
                'types' => self::TYPES,
                'statuts' => self::STATUTS,
                'communes' => $communesByInsee,
            ],
        ]);
    }

    /**
     * Full list of owners (deduplicated by name across their possibly several
     * proprietaire_version records) that qualify for the given filters: the
     * owner's own name must match the name filters, and they must have at
     * least one installation matching the other filters. Each returned owner
     * carries the FULL list of their installation ids (not just the ones
     * matching the filters) — the UI shows everything about an owner once
     * they're found, not just the matching slice.
     *
     * @return Collection<int, array{nom: string, contact: ?string, installation_ids: Collection<int, string>}>
     */
    private function ownersMatching(array $filters): Collection
    {
        $links = DB::table('installation_proprietaire_courante as ipc')
            ->join('proprietaire_versions as pv', 'pv.id', '=', 'ipc.proprietaire_version_id')
            ->select('ipc.installation_id', 'pv.nom', 'pv.prenom', 'pv.contact')
            ->get();

        $owners = $links
            ->groupBy(fn ($row) => trim($row->nom.' '.($row->prenom ?? '')))
            ->map(fn ($rows, $nom) => [
                'nom' => $nom,
                'contact' => $rows->pluck('contact')->filter()->first(),
                'installation_ids' => $rows->pluck('installation_id')->unique()->values(),
            ])
            ->values();

        $hasOtherFilters = collect(['type', 'statut', 'commune', 'adresse', 'parcelle', 'compteur'])
            ->contains(fn ($key) => filled($filters[$key] ?? null));

        $otherFilterIds = null;
        if ($hasOtherFilters) {
            $query = Installation::query();
            $this->applyInstallationFilters($query, $filters);
            $otherFilterIds = $query->pluck('id');
        }

        $q = $filters['q'] ?? null;
        $qInstallationIds = null;
        if ($q) {
            $query = Installation::query();
            $this->applyGlobalSearchOnInstallationFields($query, $q);
            $qInstallationIds = $query->pluck('id');
        }

        $proprietaire = $filters['proprietaire'] ?? null;

        return $owners->filter(function ($owner) use ($otherFilterIds, $q, $qInstallationIds, $proprietaire) {
            if ($proprietaire && ! str_contains(mb_strtolower($owner['nom']), mb_strtolower($proprietaire))) {
                return false;
            }

            if ($otherFilterIds !== null && $owner['installation_ids']->intersect($otherFilterIds)->isEmpty()) {
                return false;
            }

            if ($q) {
                $matchesName = str_contains(mb_strtolower($owner['nom']), mb_strtolower($q));
                $matchesInstallation = $owner['installation_ids']->intersect($qInstallationIds)->isNotEmpty();

                if (! $matchesName && ! $matchesInstallation) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    /**
     * Narrows $query to installations matching type/statut/commune/adresse/
     * parcelle/compteur — the criteria that describe an installation itself,
     * as opposed to who owns it.
     */
    private function applyInstallationFilters(Builder $query, array $filters): void
    {
        if ($type = $filters['type'] ?? null) {
            $query->whereHas('etatCourant', fn ($q) => $q->where('type', $type));
        }

        if ($statut = $filters['statut'] ?? null) {
            $query->whereHas('etatCourant', fn ($q) => $q->where('statut', $statut));
        }

        if ($commune = $filters['commune'] ?? null) {
            $query->whereHas('parcellesCourantes', function ($q) use ($commune) {
                $q->whereIn('parcelle_id', function ($sub) use ($commune) {
                    $sub->select('parcelle_id')->from('parcelle_versions')->where('commune_insee', $commune);
                });
            });
        }

        if ($parcelle = $filters['parcelle'] ?? null) {
            $query->whereHas('parcellesCourantes', function ($q) use ($parcelle) {
                $q->where('parcelle_id', 'like', "%{$parcelle}%")
                    ->orWhereIn('parcelle_id', function ($sub) use ($parcelle) {
                        $sub->select('parcelle_id')->from('parcelle_versions')
                            ->where('section', 'like', "%{$parcelle}%")
                            ->orWhere('numero', 'like', "%{$parcelle}%");
                    });
            });
        }

        if ($compteur = $filters['compteur'] ?? null) {
            $query->whereHas('compteursCourants', fn ($q) => $q->where('numero_compteur', 'like', "%{$compteur}%"));
        }

        if ($adresse = $filters['adresse'] ?? null) {
            $parcelleIds = $this->parcelleIdsForAdresse($adresse);

            $query->whereHas('parcellesCourantes', fn ($q) => $q->whereIn('parcelle_id', $parcelleIds));
        }
    }

    /**
     * The installation-side half of the global search box: id, parcelle,
     * compteur or address. The owner-name half is checked separately against
     * the owner's own name in ownersMatching().
     */
    private function applyGlobalSearchOnInstallationFields(Builder $query, string $q): void
    {
        $parcelleIdsFromAdresse = $this->parcelleIdsForAdresse($q);

        $query->where(function ($query) use ($q, $parcelleIdsFromAdresse) {
            $query->where('installations.id', 'like', "%{$q}%")
                ->orWhereHas('compteursCourants', fn ($sub) => $sub->where('numero_compteur', 'like', "%{$q}%"))
                ->orWhereHas('parcellesCourantes', function ($sub) use ($q, $parcelleIdsFromAdresse) {
                    $sub->where('parcelle_id', 'like', "%{$q}%")
                        ->orWhereIn('parcelle_id', function ($inner) use ($q) {
                            $inner->select('parcelle_id')->from('parcelle_versions')
                                ->where('section', 'like', "%{$q}%")
                                ->orWhere('numero', 'like', "%{$q}%");
                        })
                        ->orWhereIn('parcelle_id', $parcelleIdsFromAdresse);
                });
        });
    }

    private function parcelleIdsForAdresse(string $search): Collection
    {
        return AdresseVersion::query()
            ->where(function ($q) use ($search) {
                $q->where('nom_voie', 'like', "%{$search}%")
                    ->orWhere('code_postal', 'like', "%{$search}%")
                    ->orWhere('nom_commune', 'like', "%{$search}%");
            })
            ->pluck('cad_parcelles')
            ->filter()
            ->flatMap(fn ($ids) => $ids)
            ->unique()
            ->values();
    }

    private function adresseMapFor(Collection $installations): array
    {
        $parcelleIds = $installations
            ->flatMap(fn (Installation $i) => $i->parcellesCourantes->pluck('parcelle_id'))
            ->unique()
            ->values();

        if ($parcelleIds->isEmpty()) {
            return [];
        }

        $map = [];

        AdresseVersion::query()
            ->where(function ($q) use ($parcelleIds) {
                foreach ($parcelleIds as $parcelleId) {
                    $q->orWhereJsonContains('cad_parcelles', $parcelleId);
                }
            })
            ->get()
            ->each(function (AdresseVersion $adresse) use (&$map) {
                foreach (($adresse->cad_parcelles ?? []) as $parcelleId) {
                    $map[$parcelleId][] = trim("{$adresse->numero} {$adresse->nom_voie}").", {$adresse->code_postal} {$adresse->nom_commune}";
                }
            });

        return $map;
    }

    private function parcelleInfoFor(Collection $installations): array
    {
        $parcelleIds = $installations
            ->flatMap(fn (Installation $i) => $i->parcellesCourantes->pluck('parcelle_id'))
            ->unique()
            ->values();

        if ($parcelleIds->isEmpty()) {
            return [];
        }

        return ParcelleVersion::query()
            ->whereIn('parcelle_id', $parcelleIds)
            ->orderByDesc('id')
            ->get()
            ->unique('parcelle_id')
            ->mapWithKeys(fn (ParcelleVersion $p) => [
                $p->parcelle_id => ['ref' => "{$p->section}{$p->numero}", 'commune_insee' => $p->commune_insee],
            ])
            ->all();
    }

    private function present(Installation $installation, array $adresseMap, array $parcelleInfo, Collection $communesByInsee): array
    {
        $parcelleIds = $installation->parcellesCourantes->pluck('parcelle_id');
        $codeInsee = $parcelleIds->isNotEmpty() ? ($parcelleInfo[$parcelleIds->first()]['commune_insee'] ?? null) : null;

        $proprietaires = $installation->proprietairesCourants
            ->map(fn ($lien) => trim("{$lien->proprietaireVersion?->nom} {$lien->proprietaireVersion?->prenom}"))
            ->filter()
            ->unique();

        $batimentType = $installation->batimentsCourants
            ->map(fn ($lien) => self::BATIMENT_TYPES[$lien->batimentVersion?->type] ?? null)
            ->filter()
            ->first();

        return [
            'id' => $installation->id,
            'proprietaires' => $proprietaires->implode(', ') ?: null,
            'batiment_type' => $batimentType,
            'type' => $installation->etatCourant?->type,
            'statut' => $installation->etatCourant?->statut,
            'commune' => $codeInsee ? ($communesByInsee[$codeInsee] ?? null) : null,
            'code_insee' => $codeInsee,
            'adresse' => $parcelleIds
                ->flatMap(fn ($id) => $adresseMap[$id] ?? [])
                ->unique()
                ->implode(' | ') ?: null,
            'parcelles' => $parcelleIds->map(fn ($id) => $parcelleInfo[$id]['ref'] ?? $id)->implode(', ') ?: null,
            'compteurs' => $installation->compteursCourants->pluck('numero_compteur')->implode(', ') ?: null,
            'parcelles_count' => $installation->parcelles_courantes_count,
            'batiments_count' => $installation->batiments_courants_count,
            'compteurs_count' => $installation->compteurs_courants_count,
            'proprietaires_count' => $proprietaires->count(),
            'created_at' => $installation->created_at->format('d/m/Y H:i'),
        ];
    }
}
