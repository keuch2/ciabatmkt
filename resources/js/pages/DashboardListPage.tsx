import { PageHeader } from '@/ui/PageHeader';

/** Semana 2: se reemplaza por el listado real desde GET /api/dashboards. */
export function DashboardListPage() {
    return (
        <div>
            <PageHeader title="Dashboards" description="Dashboards publicados disponibles para tu usuario." />
            <div className="rounded border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">
                Todavía no hay dashboards publicados.
            </div>
        </div>
    );
}
