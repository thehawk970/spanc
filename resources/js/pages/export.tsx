import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

export default function Export() {
    return (
        <>
            <Head title="Export" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="max-w-xl space-y-4">
                    <div>
                        <h1 className="text-lg font-semibold">
                            Export des installations
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Tableau récapitulatif au format Excel, une feuille
                            par commune, une ligne par installation :
                            propriétaire(s), adresse, parcelle(s), commune,
                            type, statut, date et conclusion du dernier
                            rapport.
                        </p>
                    </div>

                    <Button asChild>
                        <a href="/export/installations.xlsx" download>
                            <Download />
                            Télécharger le fichier Excel
                        </a>
                    </Button>
                </div>
            </div>
        </>
    );
}

Export.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
        {
            title: 'Export',
            href: '/export',
        },
    ],
};
