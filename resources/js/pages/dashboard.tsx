import { Head } from '@inertiajs/react';
import {
    ProprietairesTable,
    type DashboardFilterOptions,
    type DashboardFilters,
    type ProprietairesPage,
} from '@/components/proprietaires-table';
import { dashboard } from '@/routes';

export default function Dashboard({
    proprietaires,
    filters,
    filterOptions,
}: {
    proprietaires: ProprietairesPage;
    filters: DashboardFilters;
    filterOptions: DashboardFilterOptions;
}) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <ProprietairesTable
                    proprietaires={proprietaires}
                    filters={filters}
                    filterOptions={filterOptions}
                />
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
