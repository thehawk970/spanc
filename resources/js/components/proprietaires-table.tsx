import { router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronDown,
    ChevronRight,
    ExternalLink,
    X,
} from 'lucide-react';
import { Fragment, useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';

export type InstallationRow = {
    id: string;
    proprietaires: string | null;
    batiment_type: 'maison' | 'annexe' | null;
    type: string | null;
    statut: string | null;
    commune: string | null;
    code_insee: string | null;
    adresse: string | null;
    parcelles: string | null;
    compteurs: string | null;
    parcelles_count: number;
    batiments_count: number;
    compteurs_count: number;
    proprietaires_count: number;
    created_at: string;
};

export type ProprietaireRow = {
    nom: string;
    contact: string | null;
    installations_count: number;
    installations: InstallationRow[];
};

export type ProprietairesPage = {
    data: ProprietaireRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type DashboardFilters = {
    q: string;
    proprietaire: string;
    adresse: string;
    parcelle: string;
    compteur: string;
    type: string;
    statut: string;
    commune: string;
    sort: 'nom' | 'installations_count';
    direction: 'asc' | 'desc';
    per_page: number;
};

export type DashboardFilterOptions = {
    types: Record<string, string>;
    statuts: Record<string, string>;
    communes: Record<string, string>;
};

type Props = {
    proprietaires: ProprietairesPage;
    filters: DashboardFilters;
    filterOptions: DashboardFilterOptions;
};

type TextFilterKey = 'q' | 'proprietaire' | 'adresse' | 'parcelle' | 'compteur';

const TYPE_VARIANT: Record<string, 'default' | 'secondary' | 'outline'> = {
    collectif: 'default',
    non_collectif: 'secondary',
    non_determine: 'outline',
};

const STATUT_VARIANT: Record<
    string,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    a_statuer: 'outline',
    a_controler: 'secondary',
    actif: 'default',
    inactif: 'secondary',
    abandonne: 'destructive',
};

const PER_PAGE_OPTIONS = [25, 50, 100, 200];

const EMPTY_TEXT_FILTERS: Record<TextFilterKey, string> = {
    q: '',
    proprietaire: '',
    adresse: '',
    parcelle: '',
    compteur: '',
};

function buildQuery(filters: DashboardFilters, page: number) {
    const params: Record<string, string | number> = {
        sort: filters.sort,
        direction: filters.direction,
        per_page: filters.per_page,
        page,
    };

    (
        [
            'q',
            'proprietaire',
            'adresse',
            'parcelle',
            'compteur',
            'type',
            'statut',
            'commune',
        ] as const
    ).forEach((key) => {
        const value = filters[key];

        if (value) {
            params[key] = value;
        }
    });

    return params;
}

function TypeBadge({ value, label }: { value: string | null; label?: string }) {
    if (!value) {
        return <>—</>;
    }

    return (
        <Badge variant={TYPE_VARIANT[value] ?? 'outline'}>
            {label ?? value}
        </Badge>
    );
}

function StatutBadge({
    value,
    label,
}: {
    value: string | null;
    label?: string;
}) {
    if (!value) {
        return <>—</>;
    }

    return (
        <Badge variant={STATUT_VARIANT[value] ?? 'outline'}>
            {label ?? value}
        </Badge>
    );
}

function BatimentBadge({ value }: { value: InstallationRow['batiment_type'] }) {
    if (!value) {
        return <>—</>;
    }

    return (
        <Badge variant={value === 'annexe' ? 'outline' : 'secondary'}>
            {value === 'annexe' ? 'Annexe' : 'Maison'}
        </Badge>
    );
}

export function ProprietairesTable({
    proprietaires,
    filters,
    filterOptions,
}: Props) {
    const [textDraft, setTextDraft] = useState<Record<TextFilterKey, string>>({
        q: filters.q,
        proprietaire: filters.proprietaire,
        adresse: filters.adresse,
        parcelle: filters.parcelle,
        compteur: filters.compteur,
    });
    const [expanded, setExpanded] = useState<Set<string>>(new Set());
    const [hideAnnexes, setHideAnnexes] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const textDraftRef = useRef(textDraft);

    useEffect(() => {
        const next = {
            q: filters.q,
            proprietaire: filters.proprietaire,
            adresse: filters.adresse,
            parcelle: filters.parcelle,
            compteur: filters.compteur,
        };
        setTextDraft(next);
        textDraftRef.current = next;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        filters.q,
        filters.proprietaire,
        filters.adresse,
        filters.parcelle,
        filters.compteur,
    ]);

    function visit(nextFilters: DashboardFilters, page: number) {
        router.get(dashboard.url(), buildQuery(nextFilters, page), {
            only: ['proprietaires', 'filters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function updateText(key: TextFilterKey, value: string) {
        const next = { ...textDraftRef.current, [key]: value };
        textDraftRef.current = next;
        setTextDraft(next);

        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        debounceRef.current = setTimeout(() => {
            visit({ ...filters, ...textDraftRef.current }, 1);
        }, 400);
    }

    function updateSelect(key: 'type' | 'statut' | 'commune', value: string) {
        visit({ ...filters, [key]: value === 'all' ? '' : value }, 1);
    }

    function toggleSort(sortKey: 'nom' | 'installations_count') {
        const direction =
            filters.sort === sortKey && filters.direction === 'asc'
                ? 'desc'
                : 'asc';

        visit({ ...filters, sort: sortKey, direction }, 1);
    }

    function changePage(page: number) {
        visit(filters, page);
    }

    function changePerPage(perPage: number) {
        visit({ ...filters, per_page: perPage }, 1);
    }

    function resetFilters() {
        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        visit(
            {
                ...filters,
                ...EMPTY_TEXT_FILTERS,
                type: '',
                statut: '',
                commune: '',
            },
            1,
        );
    }

    function toggleExpand(nom: string) {
        setExpanded((previous) => {
            const next = new Set(previous);

            if (next.has(nom)) {
                next.delete(nom);
            } else {
                next.add(nom);
            }

            return next;
        });
    }

    const hasActiveFilters =
        Boolean(filters.q) ||
        Boolean(filters.proprietaire) ||
        Boolean(filters.adresse) ||
        Boolean(filters.parcelle) ||
        Boolean(filters.compteur) ||
        Boolean(filters.type) ||
        Boolean(filters.statut) ||
        Boolean(filters.commune);

    function SortButton({
        sortKey,
        children,
    }: {
        sortKey: 'nom' | 'installations_count';
        children: React.ReactNode;
    }) {
        const isSorted = filters.sort === sortKey;

        return (
            <button
                type="button"
                className="inline-flex items-center gap-1 hover:text-foreground"
                onClick={() => toggleSort(sortKey)}
            >
                {children}
                {isSorted ? (
                    filters.direction === 'asc' ? (
                        <ArrowUp className="size-3.5" />
                    ) : (
                        <ArrowDown className="size-3.5" />
                    )
                ) : (
                    <ArrowUpDown className="size-3.5 opacity-40" />
                )}
            </button>
        );
    }

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center gap-2">
                <Input
                    value={textDraft.q}
                    onChange={(event) => updateText('q', event.target.value)}
                    placeholder="Recherche libre : propriétaire, adresse, parcelle, compteur, id…"
                    className="max-w-sm"
                />

                {hasActiveFilters && (
                    <Button variant="outline" size="sm" onClick={resetFilters}>
                        <X className="size-4" />
                        Réinitialiser les filtres
                    </Button>
                )}

                <div className="flex items-center gap-2">
                    <Checkbox
                        id="hide-annexes"
                        checked={hideAnnexes}
                        onCheckedChange={(value) =>
                            setHideAnnexes(value === true)
                        }
                    />
                    <Label
                        htmlFor="hide-annexes"
                        className="text-sm font-normal text-muted-foreground"
                    >
                        Masquer les annexes
                    </Label>
                </div>

                <span className="ml-auto text-sm text-muted-foreground">
                    {proprietaires.total.toLocaleString('fr-FR')} propriétaire
                    {proprietaires.total > 1 ? 's' : ''}
                </span>
            </div>

            <div className="flex flex-wrap items-end gap-2 rounded-md border bg-muted/30 p-3">
                <div className="flex flex-col gap-1">
                    <span className="text-xs text-muted-foreground">Type</span>
                    <Select
                        value={filters.type || 'all'}
                        onValueChange={(value) => updateSelect('type', value)}
                    >
                        <SelectTrigger size="sm" className="h-8 w-40 text-xs">
                            <SelectValue placeholder="Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tous</SelectItem>
                            {Object.entries(filterOptions.types).map(
                                ([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex flex-col gap-1">
                    <span className="text-xs text-muted-foreground">
                        Statut
                    </span>
                    <Select
                        value={filters.statut || 'all'}
                        onValueChange={(value) => updateSelect('statut', value)}
                    >
                        <SelectTrigger size="sm" className="h-8 w-40 text-xs">
                            <SelectValue placeholder="Statut" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tous</SelectItem>
                            {Object.entries(filterOptions.statuts).map(
                                ([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex flex-col gap-1">
                    <span className="text-xs text-muted-foreground">
                        Commune
                    </span>
                    <Select
                        value={filters.commune || 'all'}
                        onValueChange={(value) =>
                            updateSelect('commune', value)
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-48 text-xs">
                            <SelectValue placeholder="Commune" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Toutes</SelectItem>
                            {Object.entries(filterOptions.communes).map(
                                ([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex flex-col gap-1">
                    <span className="text-xs text-muted-foreground">
                        Adresse
                    </span>
                    <Input
                        value={textDraft.adresse}
                        onChange={(event) =>
                            updateText('adresse', event.target.value)
                        }
                        placeholder="Voie, code postal, commune…"
                        className="h-8 w-56 text-xs"
                    />
                </div>

                <div className="flex flex-col gap-1">
                    <span className="text-xs text-muted-foreground">
                        Parcelle
                    </span>
                    <Input
                        value={textDraft.parcelle}
                        onChange={(event) =>
                            updateText('parcelle', event.target.value)
                        }
                        placeholder="Section, numéro…"
                        className="h-8 w-32 text-xs"
                    />
                </div>

                <div className="flex flex-col gap-1">
                    <span className="text-xs text-muted-foreground">
                        Compteur
                    </span>
                    <Input
                        value={textDraft.compteur}
                        onChange={(event) =>
                            updateText('compteur', event.target.value)
                        }
                        placeholder="Numéro…"
                        className="h-8 w-32 text-xs"
                    />
                </div>
            </div>

            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-8" />
                            <TableHead>
                                <SortButton sortKey="nom">
                                    Propriétaire
                                </SortButton>
                            </TableHead>
                            <TableHead>Contact</TableHead>
                            <TableHead className="text-center">
                                <SortButton sortKey="installations_count">
                                    Installations
                                </SortButton>
                            </TableHead>
                        </TableRow>
                        <TableRow>
                            <TableHead />
                            <TableHead>
                                <Input
                                    value={textDraft.proprietaire}
                                    onChange={(event) =>
                                        updateText(
                                            'proprietaire',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Nom, prénom…"
                                    className="h-8 text-xs"
                                />
                            </TableHead>
                            <TableHead />
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {proprietaires.data.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={4}
                                    className="h-24 text-center text-muted-foreground"
                                >
                                    Aucun propriétaire ne correspond à ces
                                    filtres.
                                </TableCell>
                            </TableRow>
                        )}
                        {proprietaires.data.map((owner) => {
                            const isExpanded = expanded.has(owner.nom);

                            return (
                                <Fragment key={owner.nom}>
                                    <TableRow
                                        className="cursor-pointer"
                                        onClick={() => toggleExpand(owner.nom)}
                                    >
                                        <TableCell>
                                            {isExpanded ? (
                                                <ChevronDown className="size-4 text-muted-foreground" />
                                            ) : (
                                                <ChevronRight className="size-4 text-muted-foreground" />
                                            )}
                                        </TableCell>
                                        <TableCell className="font-medium">
                                            {owner.nom}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {owner.contact ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            {owner.installations_count}
                                        </TableCell>
                                    </TableRow>
                                    {isExpanded && (
                                        <TableRow key={`${owner.nom}-detail`}>
                                            <TableCell
                                                colSpan={4}
                                                className="bg-muted/20 p-0"
                                            >
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>
                                                                Bâtiment
                                                            </TableHead>
                                                            <TableHead>
                                                                Type
                                                            </TableHead>
                                                            <TableHead>
                                                                Statut
                                                            </TableHead>
                                                            <TableHead>
                                                                Commune
                                                            </TableHead>
                                                            <TableHead>
                                                                Adresse (BAN)
                                                            </TableHead>
                                                            <TableHead>
                                                                Parcelles
                                                            </TableHead>
                                                            <TableHead>
                                                                Compteurs
                                                            </TableHead>
                                                            <TableHead className="text-center">
                                                                Parc.
                                                            </TableHead>
                                                            <TableHead className="text-center">
                                                                Bât.
                                                            </TableHead>
                                                            <TableHead className="text-center">
                                                                Cpt.
                                                            </TableHead>
                                                            <TableHead>
                                                                Créée le
                                                            </TableHead>
                                                            <TableHead />
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {owner.installations
                                                            .filter(
                                                                (
                                                                    installation,
                                                                ) =>
                                                                    !hideAnnexes ||
                                                                    installation.batiment_type !==
                                                                        'annexe',
                                                            )
                                                            .map(
                                                                (
                                                                    installation,
                                                                ) => (
                                                                    <TableRow
                                                                        key={
                                                                            installation.id
                                                                        }
                                                                    >
                                                                        <TableCell>
                                                                            <BatimentBadge
                                                                                value={
                                                                                    installation.batiment_type
                                                                                }
                                                                            />
                                                                        </TableCell>
                                                                        <TableCell>
                                                                            <TypeBadge
                                                                                value={
                                                                                    installation.type
                                                                                }
                                                                                label={
                                                                                    installation.type
                                                                                        ? filterOptions
                                                                                              .types[
                                                                                              installation
                                                                                                  .type
                                                                                          ]
                                                                                        : undefined
                                                                                }
                                                                            />
                                                                        </TableCell>
                                                                        <TableCell>
                                                                            <StatutBadge
                                                                                value={
                                                                                    installation.statut
                                                                                }
                                                                                label={
                                                                                    installation.statut
                                                                                        ? filterOptions
                                                                                              .statuts[
                                                                                              installation
                                                                                                  .statut
                                                                                          ]
                                                                                        : undefined
                                                                                }
                                                                            />
                                                                        </TableCell>
                                                                        <TableCell>
                                                                            {installation.commune ??
                                                                                '—'}
                                                                        </TableCell>
                                                                        <TableCell
                                                                            className="max-w-[260px] truncate"
                                                                            title={
                                                                                installation.adresse ??
                                                                                undefined
                                                                            }
                                                                        >
                                                                            {installation.adresse ??
                                                                                '—'}
                                                                        </TableCell>
                                                                        <TableCell
                                                                            className="max-w-[160px] truncate"
                                                                            title={
                                                                                installation.parcelles ??
                                                                                undefined
                                                                            }
                                                                        >
                                                                            {installation.parcelles ??
                                                                                '—'}
                                                                        </TableCell>
                                                                        <TableCell
                                                                            className="max-w-[140px] truncate"
                                                                            title={
                                                                                installation.compteurs ??
                                                                                undefined
                                                                            }
                                                                        >
                                                                            {installation.compteurs ??
                                                                                '—'}
                                                                        </TableCell>
                                                                        <TableCell className="text-center">
                                                                            {
                                                                                installation.parcelles_count
                                                                            }
                                                                        </TableCell>
                                                                        <TableCell className="text-center">
                                                                            {
                                                                                installation.batiments_count
                                                                            }
                                                                        </TableCell>
                                                                        <TableCell className="text-center">
                                                                            {
                                                                                installation.compteurs_count
                                                                            }
                                                                        </TableCell>
                                                                        <TableCell>
                                                                            {
                                                                                installation.created_at
                                                                            }
                                                                        </TableCell>
                                                                        <TableCell>
                                                                            <a
                                                                                href={`/admin/installations/${installation.id}`}
                                                                                target="_blank"
                                                                                rel="noreferrer"
                                                                                className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                                                                                title="Ouvrir dans le panel d'administration"
                                                                            >
                                                                                <ExternalLink className="size-3.5" />
                                                                            </a>
                                                                        </TableCell>
                                                                    </TableRow>
                                                                ),
                                                            )}
                                                    </TableBody>
                                                </Table>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </Fragment>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <span>Lignes par page</span>
                    <Select
                        value={String(filters.per_page)}
                        onValueChange={(value) => changePerPage(Number(value))}
                    >
                        <SelectTrigger size="sm" className="h-8 w-20">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {PER_PAGE_OPTIONS.map((option) => (
                                <SelectItem key={option} value={String(option)}>
                                    {option}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <span>
                        {proprietaires.from ?? 0}–{proprietaires.to ?? 0} sur{' '}
                        {proprietaires.total.toLocaleString('fr-FR')}
                    </span>
                </div>

                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={proprietaires.current_page <= 1}
                        onClick={() =>
                            changePage(proprietaires.current_page - 1)
                        }
                    >
                        Précédent
                    </Button>
                    <span className="px-2 text-sm text-muted-foreground">
                        Page {proprietaires.current_page} /{' '}
                        {proprietaires.last_page}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={
                            proprietaires.current_page >=
                            proprietaires.last_page
                        }
                        onClick={() =>
                            changePage(proprietaires.current_page + 1)
                        }
                    >
                        Suivant
                    </Button>
                </div>
            </div>
        </div>
    );
}
