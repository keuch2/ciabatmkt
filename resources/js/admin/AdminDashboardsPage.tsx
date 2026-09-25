import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { ApiError } from '@/api/client';
import { adminListDashboards, deleteDashboard, updateDashboard, type DashboardSummary } from '@/api/dashboards';
import { useRequest } from '@/app/useRequest';
import { useMenu } from '@/menu/MenuProvider';
import { DashboardIcon } from '@/ui/icons';
import { dashboardHtmlPath } from '@/api/admin';
import { withBase } from '@/app/basePath';
import { ActionsMenu } from '@/ui/ActionsMenu';
import { Alert } from '@/ui/Alert';
import { Button } from '@/ui/Button';
import { PageHeader } from '@/ui/PageHeader';
import { Spinner } from '@/ui/Spinner';

function formatDate(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleString('es-PY', { dateStyle: 'short', timeStyle: 'short' });
}

export function AdminDashboardsPage() {
    const { data, error, loading, reload } = useRequest(adminListDashboards, []);
    const { reload: reloadMenu } = useMenu();
    const location = useLocation();
    const navigate = useNavigate();
    const [notice, setNotice] = useState<string | null>((location.state as { notice?: string } | null)?.notice ?? null);
    const [actionError, setActionError] = useState<string | null>(null);
    const [busy, setBusy] = useState<string | null>(null);

    async function run(id: string, action: () => Promise<unknown>, done: string) {
        setBusy(id);
        setActionError(null);
        try {
            await action();
            setNotice(done);
            reload();
            void reloadMenu();
        } catch (e) {
            setActionError(e instanceof ApiError ? e.message : 'No se pudo conectar con el servidor.');
        } finally {
            setBusy(null);
        }
    }

    function togglePublish(d: DashboardSummary) {
        void run(d.id, () => updateDashboard(d.id, { is_published: !d.is_published }), d.is_published ? `«${d.title}» pasó a borrador.` : `«${d.title}» quedó publicado.`);
    }

    function remove(d: DashboardSummary) {
        if (!window.confirm(`¿Eliminar «${d.title}»?\n\nLos datos cargados por los usuarios y su historial dejan de estar disponibles en la plataforma. Quedan archivados en el servidor y sólo un técnico puede restaurarlos.`)) return;
        void run(d.id, () => deleteDashboard(d.id), `«${d.title}» fue eliminado.`);
    }

    return (
        <div>
            <PageHeader
                title="Dashboards"
                description="Todos los dashboards. En Editar se define el ícono, quién lo ve y si está publicado."
                actions={<Button onClick={() => navigate('/admin/dashboards/new')}>Cargar dashboard</Button>}
            />

            {notice && (
                <div className="mb-3">
                    <Alert tone="success">{notice}</Alert>
                </div>
            )}
            {actionError && (
                <div className="mb-3">
                    <Alert tone="error">{actionError}</Alert>
                </div>
            )}
            {loading && <Spinner />}
            {error && <Alert tone="error">{error}</Alert>}

            {data && (
                <div className="overflow-x-auto rounded border border-slate-200 bg-white">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-3 py-2">Título</th>
                                <th className="px-3 py-2">Id</th>
                                <th className="px-3 py-2">Versión</th>
                                <th className="px-3 py-2">Parámetros</th>
                                <th className="px-3 py-2">Estado</th>
                                <th className="px-3 py-2">Visibilidad</th>
                                <th className="px-3 py-2">Actualizado</th>
                                <th className="px-3 py-2 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {data.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="px-3 py-8 text-center text-slate-500">
                                        No hay dashboards cargados.
                                    </td>
                                </tr>
                            )}
                            {data.map((d) => (
                                <tr key={d.id} className="hover:bg-slate-50">
                                    <td className="px-3 py-2 font-medium text-slate-900">
                                        <Link to={`/dashboards/${d.id}`} className="hover:underline">
                                            {d.title}
                                        </Link>
                                        {d.description && <span className="block max-w-xs truncate text-xs font-normal text-slate-500">{d.description}</span>}
                                    </td>
                                    <td className="px-3 py-2 font-mono text-xs text-slate-600">{d.slug}</td>
                                    <td className="px-3 py-2">{d.version}</td>
                                    <td className="px-3 py-2">{d.param_count}</td>
                                    <td className="px-3 py-2">
                                        <span
                                            className={`rounded px-1.5 py-0.5 text-xs ${
                                                d.is_published ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-700'
                                            }`}
                                        >
                                            {d.is_published ? 'Publicado' : 'Borrador'}
                                        </span>
                                        {(d.failures_7d ?? 0) > 0 && (
                                            <Link to={`/admin/dashboards/${d.id}/diagnostics`} className="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-800 hover:bg-red-200" title="Escrituras rechazadas en los últimos 7 días">
                                                {d.failures_7d} rechazada{d.failures_7d === 1 ? '' : 's'}
                                            </Link>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-xs">
                                        <Link to={`/admin/dashboards/${d.id}/edit`} className="flex items-center gap-2 rounded px-1 py-0.5 hover:bg-slate-100" title="Editar: ícono, quién lo ve y publicación">
                                            <DashboardIcon icon={d.icon} title={d.title} className="h-6 w-6" />
                                            <span>
                                                {d.visible_to_all && <span className="block text-slate-700">Toda la empresa</span>}
                                                {(d.divisions ?? []).length > 0 && <span className="block text-slate-700">{(d.divisions ?? []).map((x) => x.name).join(', ')}</span>}
                                                {(d.groups ?? []).length > 0 && (
                                                    <span className="block text-slate-500">{(d.groups ?? []).map((g) => `${g.name} (${g.division_name ?? 'grupo'})`).join(', ')}</span>
                                                )}
                                                {!d.visible_to_all && (d.divisions ?? []).length === 0 && (d.groups ?? []).length === 0 && (
                                                    <span className="rounded bg-amber-100 px-1.5 py-0.5 text-amber-800">Sin asignar</span>
                                                )}
                                            </span>
                                        </Link>
                                    </td>
                                    <td className="px-3 py-2 text-xs text-slate-500">{formatDate(d.updated_at)}</td>
                                    <td className="px-3 py-2">
                                        <div className="flex items-center justify-end gap-1 whitespace-nowrap">
                                            <Button variant="ghost" onClick={() => navigate(`/admin/dashboards/${d.id}/edit`)}>
                                                Editar
                                            </Button>
                                            <Button variant="ghost" onClick={() => navigate(`/admin/dashboards/${d.id}/data`)}>
                                                Datos
                                            </Button>
                                            <ActionsMenu
                                                items={[
                                                    { label: 'Diagnóstico', onClick: () => navigate(`/admin/dashboards/${d.id}/diagnostics`) },
                                                    { label: 'Descargar HTML', onClick: () => window.open(withBase(dashboardHtmlPath(d.id)), '_blank') },
                                                    { label: 'Actualizar archivo', onClick: () => navigate(`/admin/dashboards/${d.id}/update`) },
                                                    { label: 'Historial', onClick: () => navigate(`/admin/history?dashboard=${d.id}`) },
                                                    ...(d.param_count > 0
                                                        ? [
                                                              { label: 'Valores base', onClick: () => navigate(`/admin/dashboards/${d.id}/base`) },
                                                              { label: 'Escenarios', onClick: () => navigate(`/admin/dashboards/${d.id}/overview`) },
                                                          ]
                                                        : []),
                                                    { label: d.is_published ? 'Despublicar' : 'Publicar', onClick: () => togglePublish(d), disabled: busy === d.id },
                                                    { label: 'Eliminar', onClick: () => remove(d), tone: 'danger' as const, disabled: busy === d.id },
                                                ]}
                                            />
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
