import { Link } from 'react-router-dom';
import type { MenuDashboard } from '@/api/menu';
import { useAuth } from '@/auth/AuthProvider';
import { useMenu } from '@/menu/MenuProvider';
import { Alert } from '@/ui/Alert';
import { DashboardIcon } from '@/ui/icons';
import { PageHeader } from '@/ui/PageHeader';
import { Spinner } from '@/ui/Spinner';

function Cards({ dashboards }: { dashboards: MenuDashboard[] }) {
    return (
        <ul className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            {dashboards.map((d) => (
                <li key={d.id}>
                    <Link to={`/dashboards/${d.id}`} className="flex h-full items-center gap-3 rounded border border-slate-200 bg-white px-4 py-3 transition-colors hover:border-slate-400">
                        <DashboardIcon icon={d.icon} title={d.title} className="h-9 w-9 text-sm" />
                        <span className="min-w-0">
                            <span className="block truncate font-medium text-slate-900">{d.title}</span>
                            {d.description && <span className="mt-0.5 line-clamp-2 block text-xs text-slate-500">{d.description}</span>}
                            {!d.is_published && <span className="text-xs text-amber-700">borrador</span>}
                        </span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}

/** Inicio: los dashboards del usuario, agrupados como en el menú. */
export function DashboardListPage() {
    const { user } = useAuth();
    const { menu, loading, error } = useMenu();
    const empty = !!menu && menu.divisions.length === 0 && menu.company.length === 0 && menu.unassigned.length === 0;

    return (
        <div>
            <PageHeader title={`Bienvenido${user?.name ? `, ${user.name}` : ''}`} />

            {loading && <Spinner />}
            {error && <Alert tone="error">{error}</Alert>}

            {empty && (
                <div className="rounded border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">
                    {user?.role === 'super_admin'
                        ? 'Todavía no hay dashboards. Cargá uno desde Administración → Dashboards.'
                        : 'No tenés dashboards asignados. Pedile al administrador que te asigne a una división.'}
                </div>
            )}

            {menu && (
                <div className="space-y-6">
                    {menu.divisions.map((division) => (
                        <section key={division.id}>
                            <h2 className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{division.name}</h2>
                            {division.dashboards.length > 0 && <Cards dashboards={division.dashboards} />}
                            {division.groups.map((group) => (
                                <div key={group.id} className="mt-3">
                                    <h3 className="mb-2 text-xs font-medium text-slate-500">{group.name}</h3>
                                    <Cards dashboards={group.dashboards} />
                                </div>
                            ))}
                        </section>
                    ))}
                    {menu.company.length > 0 && (
                        <section>
                            <h2 className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Toda la empresa</h2>
                            <Cards dashboards={menu.company} />
                        </section>
                    )}
                    {menu.unassigned.length > 0 && (
                        <section>
                            <h2 className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Sin asignar (sólo administradores)</h2>
                            <Cards dashboards={menu.unassigned} />
                        </section>
                    )}
                </div>
            )}
        </div>
    );
}
