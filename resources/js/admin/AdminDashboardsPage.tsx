import { useState } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { ApiError } from '@/api/client';
import { adminListDashboards, deleteDashboard, updateDashboard, type DashboardSummary } from '@/api/dashboards';
import { useRequest } from '@/app/useRequest';
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
        if (!window.confirm(`¿Eliminar «${d.title}»? Se borran también los valores guardados por los usuarios y su historial.`)) return;
        void run(d.id, () => deleteDashboard(d.id), `«${d.title}» fue eliminado.`);
    }

    return (
        <div>
            <PageHeader
                title="Dashboards"
                description="Publicación y estado de todos los dashboards."
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
                                <th className="px-3 py-2">Actualizado</th>
                                <th className="px-3 py-2 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {data.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-3 py-8 text-center text-slate-500">
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
                                    </td>
                                    <td className="px-3 py-2 text-xs text-slate-500">{formatDate(d.updated_at)}</td>
                                    <td className="px-3 py-2">
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" onClick={() => navigate(`/admin/dashboards/${d.id}/update`)}>
                                                Actualizar
                                            </Button>
                                            <Button variant="ghost" loading={busy === d.id} onClick={() => togglePublish(d)}>
                                                {d.is_published ? 'Despublicar' : 'Publicar'}
                                            </Button>
                                            <Button variant="ghost" className="text-red-700" loading={busy === d.id} onClick={() => remove(d)}>
                                                Eliminar
                                            </Button>
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
