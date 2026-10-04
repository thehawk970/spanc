import { Head } from '@inertiajs/react';
import {
    RapprochementsTable,
    type PropositionsPage,
    type RapprochementFilterOptions,
    type RapprochementFilters,
} from '@/components/rapprochements-table';

export default function Rapprochements({
    propositions,
    filters,
    filterOptions,
    total_en_attente: totalEnAttente,
}: {
    propositions: PropositionsPage;
    filters: RapprochementFilters;
    filterOptions: RapprochementFilterOptions;
    total_en_attente: number;
}) {
    return (
        <>
            <Head title="Rapprochements à valider" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <RapprochementsTable
                    propositions={propositions}
                    filters={filters}
                    filterOptions={filterOptions}
                    totalEnAttente={totalEnAttente}
                />
            </div>
        </>
    );
}

Rapprochements.layout = {
    breadcrumbs: [
        {
            title: 'Rapprochements à valider',
            href: '/rapprochements',
        },
    ],
};
