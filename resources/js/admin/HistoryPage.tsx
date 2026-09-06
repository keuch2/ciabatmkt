import { useEffect, useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { getAdminHistory, listUsers, type HistoryFilters } from '@/api/admin';
import { adminListDashboards, getDashboard } from '@/api/dashboards';
import { useRequest } from '@/app/useRequest';
import { Alert } from '@/ui/Alert';
import { Button } from '@/ui/Button';
import { Input } from '@/ui/Input';
import { PageHeader } from '@/ui/PageHeader';
import { Select } from '@/ui/Select';
import { Spinner } from '@/ui/Spinner';
import { HistoryTable, Pagination } from './HistoryTable';

/** Historial completo, filtrable por dashboard, parámetro, usuario, nivel, acción y fechas. */
export function HistoryPage() {
    const [search, setSearch] = useSearchParams();
    const dashboardId = search.get('dashboard') ?? '';

    const dashboards = useRequest(adminListDashboards, []);
    const users = useRequest(listUsers, []);
    const detail = useRequest(() => (dashboardId ? getDashboard(dashboardId) : Promise.resolve(null)), [dashboardId]);

    const [filters, setFilters] = useState<HistoryFilters>({ page: 1 });
    useEffect(() => setFilters({ page: 1 }), [dashboardId]);

    const history = useRequest(() => (dashboardId ? getAdminHistory(dashboardId, filters) : Promise.resolve(null)), [dashboardId, filters]);

    const params = useMemo(() => detail.data?.manifest.params ?? [], [detail.data]);

    function setFilter<K extends keyof HistoryFilters>(key: K, value: HistoryFilters[K]) {
        setFilters((f) => ({ ...f, [key]: value, page: key === 'page' ? (value as number) : 1 }));
    }

    return (
        <div>
            <PageHeader title="Historial" description="Todos los cambios de valores, por trigger de base de datos. Nada se edita ni se borra de acá." />

            <div className="mb-3 grid grid-cols-2 gap-2 rounded border border-slate-200 bg-white p-3 md:grid-cols-4 xl:grid-cols-7">
                <label className="text-xs text-slate-600 md:col-span-2">
                    Dashboard
                    <Select value={dashboardId} onChange={(e) => setSearch(e.target.value ? { dashboard: e.target.value } : {})}>
                        <option value="">Elegí un dashboard…</option>
                        {dashboards.data?.map((d) => (
                            <option key={d.id} value={d.id}>
                                {d.title} (v{d.version})
                            </option>
                        ))}
                    </Select>
                </label>
                <label className="text-xs text-slate-600">
                    Parámetro
                    <Select value={filters.param_id ?? ''} disabled={!dashboardId} onChange={(e) => setFilter('param_id', e.target.value)}>
                        <option value="">Todos</option>
                        {params.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.label}
                            </option>
                        ))}
                    </Select>
                </label>
                <label className="text-xs text-slate-600">
                    Usuario
                    <Select value={filters.user_id ?? ''} disabled={!dashboardId} onChange={(e) => setFilter('user_id', e.target.value)}>
                        <option value="">Todos</option>
                        {users.data?.map((u) => (
                            <option key={u.id} value={u.id}>
                                {u.name}
                            </option>
                        ))}
                    </Select>
                </label>
                <label className="text-xs text-slate-600">
                    Nivel
                    <Select value={filters.scope ?? ''} disabled={!dashboardId} onChange={(e) => setFilter('scope', e.target.value as HistoryFilters['scope'])}>
                        <option value="">Todos</option>
                        <option value="user">Usuarios</option>
                        <option value="base">Valores base</option>
                    </Select>
                </label>
                <label className="text-xs text-slate-600">
                    Desde
                    <Input type="date" value={filters.from ?? ''} disabled={!dashboardId} onChange={(e) => setFilter('from', e.target.value)} />
                </label>
                <label className="text-xs text-slate-600">
                    Hasta
                    <Input type="date" value={filters.to ?? ''} disabled={!dashboardId} onChange={(e) => setFilter('to', e.target.value)} />
                </label>
            </div>

            {!dashboardId && <p className="text-sm text-slate-500">Elegí un dashboard para ver su historial.</p>}
            {(history.loading || detail.loading) && dashboardId && <Spinner />}
            {history.error && <Alert tone="error">{history.error}</Alert>}

            {history.data && (
                <div className="overflow-x-auto rounded border border-slate-200 bg-white">
                    <HistoryTable entries={history.data.data} />
                    <Pagination page={history.data.meta.current_page} lastPage={history.data.meta.last_page} total={history.data.meta.total} onChange={(p) => setFilter('page', p)} />
                </div>
            )}

            {dashboardId && (filters.param_id || filters.user_id || filters.scope || filters.from || filters.to) && (
                <div className="mt-2">
                    <Button variant="ghost" onClick={() => setFilters({ page: 1 })}>
                        Limpiar filtros
                    </Button>
                </div>
            )}
        </div>
    );
}
