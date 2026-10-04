import { router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Check,
    CheckCheck,
    X,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
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
import { Textarea } from '@/components/ui/textarea';

export type PropositionRow = {
    id: number;
    installation_id: string | null;
    installation_commune: string | null;
    installation_parcelle: string | null;
    type_cible: 'proprietaire' | 'compteur';
    cible_label: string;
    methode: string;
    confiance: number;
    created_at: string;
};

export type PropositionsPage = {
    data: PropositionRow[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type RapprochementFilters = {
    type_cible: string;
    methode: string;
    sort: 'confiance' | 'created_at';
    direction: 'asc' | 'desc';
    per_page: number;
};

export type RapprochementFilterOptions = {
    types_cible: Record<string, string>;
    methodes: Record<string, string>;
};

type Props = {
    propositions: PropositionsPage;
    filters: RapprochementFilters;
    filterOptions: RapprochementFilterOptions;
    totalEnAttente: number;
};

const TYPE_BADGE: Record<string, 'default' | 'secondary'> = {
    proprietaire: 'default',
    compteur: 'secondary',
};

const PER_PAGE_OPTIONS = [25, 50, 100];

function buildQuery(filters: RapprochementFilters, page: number) {
    const params: Record<string, string | number> = {
        sort: filters.sort,
        direction: filters.direction,
        per_page: filters.per_page,
        page,
    };

    if (filters.type_cible) {
        params.type_cible = filters.type_cible;
    }

    if (filters.methode) {
        params.methode = filters.methode;
    }

    return params;
}

function RejeterDialog({ propositionId }: { propositionId: number }) {
    const [motif, setMotif] = useState('');
    const [open, setOpen] = useState(false);

    function submit() {
        router.post(
            `/rapprochements/${propositionId}/rejeter`,
            { motif },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setMotif('');
                    setOpen(false);
                },
            },
        );
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <X className="size-4" />
                    Rejeter
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Rejeter ce rapprochement ?</DialogTitle>
                <DialogDescription>
                    La proposition n'est pas modifiée, seule la décision est
                    enregistrée. Un motif est requis.
                </DialogDescription>

                <div className="grid gap-2">
                    <Label htmlFor="motif">Motif du rejet</Label>
                    <Textarea
                        id="motif"
                        value={motif}
                        onChange={(event) => setMotif(event.target.value)}
                        placeholder="Ex : parcelle partagée avec un tiers sans lien réel"
                    />
                </div>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Annuler</Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        disabled={motif.trim() === ''}
                        onClick={submit}
                    >
                        Rejeter
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export function RapprochementsTable({
    propositions,
    filters,
    filterOptions,
    totalEnAttente,
}: Props) {
    function visit(nextFilters: RapprochementFilters, page: number) {
        router.get('/rapprochements', buildQuery(nextFilters, page), {
            only: ['propositions', 'filters'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function updateSelect(key: 'type_cible' | 'methode', value: string) {
        visit({ ...filters, [key]: value === 'all' ? '' : value }, 1);
    }

    function toggleSort(sortKey: 'confiance' | 'created_at') {
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

    function valider(id: number) {
        router.post(
            `/rapprochements/${id}/valider`,
            {},
            { preserveScroll: true },
        );
    }

    function validerTout() {
        router.post(
            '/rapprochements/valider-tout',
            {
                type_cible: filters.type_cible || undefined,
                methode: filters.methode || undefined,
            },
            { preserveScroll: true },
        );
    }

    function SortButton({
        sortKey,
        children,
    }: {
        sortKey: 'confiance' | 'created_at';
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
                <div className="flex flex-col gap-1">
                    <span className="text-xs text-muted-foreground">Type</span>
                    <Select
                        value={filters.type_cible || 'all'}
                        onValueChange={(value) =>
                            updateSelect('type_cible', value)
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-44 text-xs">
                            <SelectValue placeholder="Type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Tous</SelectItem>
                            {Object.entries(filterOptions.types_cible).map(
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
                        Méthode
                    </span>
                    <Select
                        value={filters.methode || 'all'}
                        onValueChange={(value) =>
                            updateSelect('methode', value)
                        }
                    >
                        <SelectTrigger size="sm" className="h-8 w-56 text-xs">
                            <SelectValue placeholder="Méthode" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Toutes</SelectItem>
                            {Object.entries(filterOptions.methodes).map(
                                ([value, label]) => (
                                    <SelectItem key={value} value={value}>
                                        {label}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                </div>

                <span className="ml-auto text-sm text-muted-foreground">
                    {totalEnAttente.toLocaleString('fr-FR')} en attente au total
                </span>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="default"
                            size="sm"
                            disabled={propositions.total === 0}
                        >
                            <CheckCheck className="size-4" />
                            Tout valider
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            Tout valider la file en attente ?
                        </DialogTitle>
                        <DialogDescription>
                            {propositions.total} proposition(s) seront validées
                            en une fois (selon les filtres actifs), y compris
                            celles à confiance réduite. À utiliser après avoir
                            parcouru la liste, pas à la place.
                        </DialogDescription>
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Annuler</Button>
                            </DialogClose>
                            <DialogClose asChild>
                                <Button onClick={validerTout}>
                                    Tout valider
                                </Button>
                            </DialogClose>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>

            <div className="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Installation</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Cible proposée</TableHead>
                            <TableHead>Méthode</TableHead>
                            <TableHead>
                                <SortButton sortKey="confiance">
                                    Confiance
                                </SortButton>
                            </TableHead>
                            <TableHead>
                                <SortButton sortKey="created_at">
                                    Proposé le
                                </SortButton>
                            </TableHead>
                            <TableHead />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {propositions.data.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={7}
                                    className="h-24 text-center text-muted-foreground"
                                >
                                    Aucun rapprochement en attente.
                                </TableCell>
                            </TableRow>
                        )}
                        {propositions.data.map((proposition) => (
                            <TableRow key={proposition.id}>
                                <TableCell className="text-muted-foreground">
                                    {(proposition.installation_commune ??
                                    proposition.installation_parcelle) ? (
                                        <>
                                            {proposition.installation_commune ??
                                                '—'}
                                            {proposition.installation_parcelle
                                                ? ` · ${proposition.installation_parcelle}`
                                                : ''}
                                        </>
                                    ) : (
                                        '—'
                                    )}
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        variant={
                                            TYPE_BADGE[
                                                proposition.type_cible
                                            ] ?? 'outline'
                                        }
                                    >
                                        {proposition.type_cible ===
                                        'proprietaire'
                                            ? 'Propriétaire'
                                            : 'Compteur'}
                                    </Badge>
                                </TableCell>
                                <TableCell
                                    className="max-w-[320px] truncate"
                                    title={proposition.cible_label}
                                >
                                    {proposition.cible_label}
                                </TableCell>
                                <TableCell className="text-muted-foreground">
                                    {proposition.methode}
                                </TableCell>
                                <TableCell>
                                    {proposition.confiance.toFixed(2)}
                                </TableCell>
                                <TableCell className="text-muted-foreground">
                                    {proposition.created_at}
                                </TableCell>
                                <TableCell>
                                    <div className="flex items-center justify-end gap-2">
                                        <Button
                                            variant="default"
                                            size="sm"
                                            onClick={() =>
                                                valider(proposition.id)
                                            }
                                        >
                                            <Check className="size-4" />
                                            Valider
                                        </Button>
                                        <RejeterDialog
                                            propositionId={proposition.id}
                                        />
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
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
                        {propositions.from ?? 0}–{propositions.to ?? 0} sur{' '}
                        {propositions.total.toLocaleString('fr-FR')}
                    </span>
                </div>

                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={propositions.current_page <= 1}
                        onClick={() =>
                            changePage(propositions.current_page - 1)
                        }
                    >
                        Précédent
                    </Button>
                    <span className="px-2 text-sm text-muted-foreground">
                        Page {propositions.current_page} /{' '}
                        {propositions.last_page}
                    </span>
                    <Button
                        variant="outline"
                        size="sm"
                        disabled={
                            propositions.current_page >= propositions.last_page
                        }
                        onClick={() =>
                            changePage(propositions.current_page + 1)
                        }
                    >
                        Suivant
                    </Button>
                </div>
            </div>
        </div>
    );
}
