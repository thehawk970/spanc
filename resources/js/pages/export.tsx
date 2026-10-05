import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';

export default function Export() {
    const [telechargement, setTelechargement] = useState(false);

    async function telecharger() {
        setTelechargement(true);

        try {
            const reponse = await fetch('/export/installations.xlsx');

            if (!reponse.ok) {
                throw new Error(`HTTP ${reponse.status}`);
            }

            const blob = await reponse.blob();
            const url = URL.createObjectURL(blob);
            const lien = document.createElement('a');
            lien.href = url;
            lien.download = `installations_recapitulatif_${new Date().toISOString().slice(0, 10)}.xlsx`;
            document.body.appendChild(lien);
            lien.click();
            lien.remove();
            URL.revokeObjectURL(url);
        } catch {
            toast.error("Échec du téléchargement de l'export, réessayez.");
        } finally {
            setTelechargement(false);
        }
    }

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

                    <Button
                        onClick={telecharger}
                        disabled={telechargement}
                    >
                        {telechargement ? <Spinner /> : <Download />}
                        {telechargement
                            ? 'Génération en cours…'
                            : 'Télécharger le fichier Excel'}
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
