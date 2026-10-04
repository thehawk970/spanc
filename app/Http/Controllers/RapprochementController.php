<?php

namespace App\Http\Controllers;

use App\Models\AdresseVersion;
use App\Models\CompteurVersion;
use App\Models\Installation;
use App\Models\ParcelleVersion;
use App\Models\ProprietaireVersion;
use App\Models\RapprochementPropose;
use App\Support\RapprochementValidation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Équivalent non-Filament de App\Filament\Pages\RapprochementsAValider,
 * pour permettre la revue sans donner accès au panel Filament (données
 * brutes, imports, commandes) à tout le monde. Même logique de décision
 * (App\Support\RapprochementValidation), juste une autre porte d'entrée.
 */
class RapprochementController extends Controller
{
    private const TYPES_CIBLE = [
        'proprietaire' => 'Propriétaire',
        'compteur' => 'Compteur',
    ];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'type_cible' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::TYPES_CIBLE))],
            'methode' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'in:confiance,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
        ]);

        $query = RapprochementPropose::query()->whereDoesntHave('decisions');

        if ($typeCible = $filters['type_cible'] ?? null) {
            $query->where('type_cible', $typeCible);
        }

        if ($methode = $filters['methode'] ?? null) {
            $query->where('methode', $methode);
        }

        $sort = $filters['sort'] ?? 'confiance';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $propositions = $query->orderBy($sort, $direction)
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();

        $propositions->setCollection(
            $propositions->getCollection()->map(fn (RapprochementPropose $p) => $this->present($p))
        );

        return Inertia::render('rapprochements', [
            'propositions' => $propositions,
            'filters' => [
                'type_cible' => $filters['type_cible'] ?? '',
                'methode' => $filters['methode'] ?? '',
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $filters['per_page'] ?? 25,
            ],
            'filterOptions' => [
                'types_cible' => self::TYPES_CIBLE,
                'methodes' => RapprochementPropose::query()->distinct()->pluck('methode', 'methode'),
            ],
            'total_en_attente' => RapprochementPropose::query()->whereDoesntHave('decisions')->count(),
        ]);
    }

    public function valider(RapprochementPropose $rapprochement): RedirectResponse
    {
        $resultat = RapprochementValidation::valider($rapprochement, auth()->id());

        $message = 'Rapprochement validé.';

        if ($resultat['resultat'] === 'determine') {
            $type = $resultat['type'] === 'collectif' ? 'Collectif' : 'Non collectif';
            $message = "Rapprochement validé. Type déduit : {$type} (via {$resultat['detail']}).";
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    public function rejeter(Request $request, RapprochementPropose $rapprochement): RedirectResponse
    {
        $data = $request->validate([
            'motif' => ['required', 'string', 'max:1000'],
        ]);

        RapprochementValidation::rejeter($rapprochement, $data['motif'], auth()->id());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Rapprochement rejeté.']);

        return back();
    }

    public function validerTout(Request $request): RedirectResponse
    {
        $filters = $request->validate([
            'type_cible' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::TYPES_CIBLE))],
            'methode' => ['nullable', 'string', 'max:255'],
        ]);

        $query = RapprochementPropose::query()->whereDoesntHave('decisions');

        if ($typeCible = $filters['type_cible'] ?? null) {
            $query->where('type_cible', $typeCible);
        }

        if ($methode = $filters['methode'] ?? null) {
            $query->where('methode', $methode);
        }

        $valides = 0;
        $typesDetermines = 0;

        DB::transaction(function () use ($query, &$valides, &$typesDetermines) {
            foreach ($query->get() as $record) {
                $resultat = RapprochementValidation::valider(
                    $record,
                    auth()->id(),
                    'Validation manuelle groupée (bouton "Tout valider")'
                );

                $valides++;

                if ($resultat['resultat'] === 'determine') {
                    $typesDetermines++;
                }
            }
        });

        $message = "{$valides} rapprochement(s) validé(s).";

        if ($typesDetermines > 0) {
            $message .= " {$typesDetermines} type(s) AC/ANC déduit(s) au passage.";
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function present(RapprochementPropose $proposition): array
    {
        $installation = $proposition->installation_id ? Installation::find($proposition->installation_id) : null;

        $contexte = null;

        if ($installation) {
            $parcelleId = DB::table('installation_parcelle_courante')
                ->where('installation_id', $installation->id)
                ->value('parcelle_id');

            $commune = null;
            $parcelleRef = null;

            if ($parcelleId) {
                $parcelle = ParcelleVersion::where('parcelle_id', $parcelleId)->latest('id')->first();
                $parcelleRef = $parcelle ? "{$parcelle->section}{$parcelle->numero}" : null;
                $commune = $parcelle ? AdresseVersion::where('code_insee', $parcelle->commune_insee)->value('nom_commune') : null;
            }

            $contexte = array_filter(['commune' => $commune, 'parcelle' => $parcelleRef]);
        }

        return [
            'id' => $proposition->id,
            'installation_id' => $proposition->installation_id,
            'installation_commune' => $contexte['commune'] ?? null,
            'installation_parcelle' => $contexte['parcelle'] ?? null,
            'type_cible' => $proposition->type_cible,
            'cible_label' => $this->libelleCible($proposition),
            'methode' => $proposition->methode,
            'confiance' => (float) $proposition->confiance,
            'created_at' => $proposition->created_at->format('d/m/Y H:i'),
        ];
    }

    private function libelleCible(RapprochementPropose $proposition): string
    {
        if ($proposition->type_cible === 'proprietaire') {
            $proprietaire = ProprietaireVersion::find($proposition->cible_id);

            return $proprietaire
                ? trim("{$proprietaire->nom} {$proprietaire->prenom}")
                : "#{$proposition->cible_id} (introuvable)";
        }

        if ($proposition->type_cible === 'compteur') {
            $compteur = CompteurVersion::where('numero_compteur', $proposition->cible_id)->latest('id')->first();

            return $compteur
                ? trim("{$compteur->civilite} {$compteur->abonne_nom_brut}")." — {$compteur->adresse_brute} (compteur {$proposition->cible_id})"
                : "compteur {$proposition->cible_id} (introuvable)";
        }

        return (string) $proposition->cible_id;
    }
}
