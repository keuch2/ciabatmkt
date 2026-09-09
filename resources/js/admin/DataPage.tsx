import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { exportPath, getAdminRecords, getDataHistory, getDataSummary, listUsers, type DataHistoryFilters } from '@/api/admin';
import { withBase } from '@/app/basePath';
import { useRequest } from '@/app/useRequest';
import { Alert } from '@/ui/Alert';
import { formatDateTime } from '@/ui/formatValue';
import { Input } from '@/ui/Input';
import { PageHeader } from '@/ui/PageHeader';
import { Select } from '@/ui/Select';
import { Spinner } from '@/ui/Spinner';
import { Pagination } from './HistoryTable';

const ACTION: Record<'insert' | 'update' | 'delete', { label: string; className: string }> = {
    insert: { label: 'Creó', className: 'bg-green-100 text-green-800' },
    update: { label: 'Modificó', className: 'bg-slate-200 text-slate-700' },
    delete: { label: 'Eliminó', className: 'bg-amber-100 text-amber-800' },
};

function preview(value: unknown, max = 140): string {
    const text = JSON.stringify(value) ?? '';
    return text.length > max ? `${text.slice(0, max)}…` : text;
}

/** Datos compartidos de un dashboard: colecciones, registros, exportación e historial. */
export function DataPage() {
    const { id = '' } = useParams();
    const summary = useRequest(() => getDataSummary(id), [id]);
    const [collection, setCollection] = useState<string>('');
    const [tab, setTab] = useState<'records' | 'history'>('records');

    return (
        <div>
            <PageHeader
                title="Datos del dashboard"
                description="Registros que los usuarios cargaron desde la interfaz del dashboard. Se comparten entre todos."
                actions={
                    <Link to="/admin/dashboards" className="text-sm text-slate-600 underline-offset-2 hover:underline">
                        Volver
                    </Link>
                }
            />

            {summary.loading && <Spinner />}
            {summary.error && <Alert tone="error">{summary.error}</Alert>}

            {summary.data && (
                <div className="mb-4 overflow-x-auto rounded border border-slate-200 bg-white">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-3 py-2">Colección</th>
                                <th className="px-3 py-2">Id</th>
                                <th className="px-3 py-2 text-right">Registros</th>
                                <th className="px-3 py-2 text-right">Máximo</th>
                                <th className="px-3 py-2">Último cambio</th>
                                <th className="px-3 py-2 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {[...summary.data.collections, ...summary.data.orphan_collections].map((c) => (
                                <tr key={c.id} className={collection === c.id ? 'bg-slate-50' : 'hover:bg-slate-50'}>
                                    <td className="px-3 py-2 font-medium text-slate-900">
                                        {c.label ?? <span className="text-slate-400">(ya no declarada)</span>}
                                    </td>
                                    <td className="px-3 py-2 font-mono text-xs">{c.id}</td>
                                    <td className="px-3 py-2 text-right tabular-nums">{c.records}</td>
                                    <td className="px-3 py-2 text-right tabular-nums text-slate-500">{c.max_records ?? '—'}</td>
                                    <td className="px-3 py-2 text-xs text-slate-500">{formatDateTime(c.last_updated_at)}</td>
                                    <td className="px-3 py-2 text-right">
                                        <button type="button" onClick={() => { setCollection(c.id); setTab('records'); }} className="text-xs text-slate-700 underline-offset-2 hover:underline">
                                            Ver registros
                                        </button>
                                        <a href={withBase(exportPath(id, c.id))} download className="ml-3 text-xs text-slate-700 underline-offset-2 hover:underline">
                                            Exportar JSON
                                        </a>
                                    </td>
                                </tr>
                            ))}
                            {summary.data.collections.length + summary.data.orphan_collections.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-3 py-6 text-center text-slate-500">
                                        Este dashboard no declara colecciones de datos.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            )}

            <div className="mb-3 flex gap-1 border-b border-slate-200">
                {(['records', 'history'] as const).map((t) => (
                    <button
                        key={t}
                        type="button"
                        onClick={() => setTab(t)}
                        className={`-mb-px border-b-2 px-3 py-2 text-sm ${tab === t ? 'border-slate-800 font-medium text-slate-900' : 'border-transparent text-slate-500 hover:text-slate-800'}`}
                    >
                        {t === 'records' ? 'Registros' : 'Historial de cambios'}
                    </button>
                ))}
            </div>

            {tab === 'records' && (collection ? <RecordsTable dashboardId={id} collection={collection} /> : <p className="text-sm text-slate-500">Elegí una colección con "Ver registros".</p>)}
            {tab === 'history' && <DataHistory dashboardId={id} collections={(summary.data?.collections ?? []).map((c) => c.id)} />}
        </div>
    );
}

function RecordsTable({ dashboardId, collection }: { dashboardId: string; collection: string }) {
    const { data, error, loading } = useRequest(() => getAdminRecords(dashboardId, collection), [dashboardId, collection]);
    const [open, setOpen] = useState<string | null>(null);

    if (loading) return <Spinner />;
    if (error || !data) return <Alert tone="error">{error ?? 'Error'}</Alert>;

    return (
        <div className="overflow-x-auto rounded border border-slate-200 bg-white">
            <table className="w-full text-sm">
                <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th className="px-3 py-2">Id</th>
                        <th className="px-3 py-2">Contenido</th>
                        <th className="px-3 py-2 text-right">Versión</th>
                        <th className="px-3 py-2">Último cambio</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                    {data.records.length === 0 && (
                        <tr>
                            <td colSpan={4} className="px-3 py-6 text-center text-slate-500">
                                La colección «{collection}» está vacía.
                            </td>
                        </tr>
                    )}
                    {data.records.map((r) => (
                        <tr key={r.id} className="align-top hover:bg-slate-50">
                            <td className="px-3 py-2 font-mono text-xs">{r.id}</td>
                            <td className="px-3 py-2 font-mono text-xs text-slate-700">
                                <button type="button" onClick={() => setOpen(open === r.id ? null : r.id)} className="text-left" title="Ver completo">
                                    {open === r.id ? <pre className="max-h-96 overflow-auto whitespace-pre-wrap">{JSON.stringify(r.data, null, 2)}</pre> : preview(r.data)}
                                </button>
                            </td>
                            <td className="px-3 py-2 text-right tabular-nums">{r.version}</td>
                            <td className="px-3 py-2 text-xs text-slate-500">
                                {formatDateTime(r.updated_at)}
                                {r.updated_by && <span className="block">{r.updated_by.name}</span>}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function DataHistory({ dashboardId, collections }: { dashboardId: string; collections: string[] }) {
    const [filters, setFilters] = useState<DataHistoryFilters>({ page: 1 });
    const users = useRequest(listUsers, []);
    const history = useRequest(() => getDataHistory(dashboardId, filters), [dashboardId, filters]);

    function setFilter<K extends keyof DataHistoryFilters>(key: K, value: DataHistoryFilters[K]) {
        setFilters((f) => ({ ...f, [key]: value, page: key === 'page' ? (value as number) : 1 }));
    }

    return (
        <div>
            <div className="mb-3 grid grid-cols-2 gap-2 rounded border border-slate-200 bg-white p-3 md:grid-cols-6">
                <label className="text-xs text-slate-600">
                    Colección
                    <Select value={filters.collection ?? ''} onChange={(e) => setFilter('collection', e.target.value)}>
                        <option value="">Todas</option>
                        {collections.map((c) => (
                            <option key={c} value={c}>
                                {c}
                            </option>
                        ))}
                    </Select>
                </label>
                <label className="text-xs text-slate-600">
                    Registro
                    <Input value={filters.record_id ?? ''} placeholder="id" onChange={(e) => setFilter('record_id', e.target.value)} />
                </label>
                <label className="text-xs text-slate-600">
                    Usuario
                    <Select value={filters.user_id ?? ''} onChange={(e) => setFilter('user_id', e.target.value)}>
                        <option value="">Todos</option>
                        {users.data?.map((u) => (
                            <option key={u.id} value={u.id}>
                                {u.name}
                            </option>
                        ))}
                    </Select>
                </label>
                <label className="text-xs text-slate-600">
                    Acción
                    <Select value={filters.action ?? ''} onChange={(e) => setFilter('action', e.target.value as DataHistoryFilters['action'])}>
                        <option value="">Todas</option>
                        <option value="insert">Creó</option>
                        <option value="update">Modificó</option>
                        <option value="delete">Eliminó</option>
                    </Select>
                </label>
                <label className="text-xs text-slate-600">
                    Desde
                    <Input type="date" value={filters.from ?? ''} onChange={(e) => setFilter('from', e.target.value)} />
                </label>
                <label className="text-xs text-slate-600">
                    Hasta
                    <Input type="date" value={filters.to ?? ''} onChange={(e) => setFilter('to', e.target.value)} />
                </label>
            </div>

            {history.loading && <Spinner />}
            {history.error && <Alert tone="error">{history.error}</Alert>}
            {history.data && (
                <div className="overflow-x-auto rounded border border-slate-200 bg-white">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-3 py-2">Fecha</th>
                                <th className="px-3 py-2">Colección</th>
                                <th className="px-3 py-2">Registro</th>
                                <th className="px-3 py-2">Acción</th>
                                <th className="px-3 py-2">Usuario</th>
                                <th className="px-3 py-2">Contenido</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {history.data.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-3 py-6 text-center text-slate-500">
                                        No hay cambios registrados con estos filtros.
                                    </td>
                                </tr>
                            )}
                            {history.data.data.map((h) => (
                                <tr key={h.id} className="align-top hover:bg-slate-50">
                                    <td className="whitespace-nowrap px-3 py-1.5 text-xs text-slate-500">{formatDateTime(h.changed_at)}</td>
                                    <td className="px-3 py-1.5 font-mono text-xs">{h.collection}</td>
                                    <td className="px-3 py-1.5 font-mono text-xs">{h.record_id}</td>
                                    <td className="px-3 py-1.5">
                                        <span className={`rounded px-1.5 py-0.5 text-xs ${ACTION[h.action].className}`}>{ACTION[h.action].label}</span>
                                    </td>
                                    <td className="px-3 py-1.5 text-xs text-slate-600">{h.changed_by?.name ?? '—'}</td>
                                    <td className="px-3 py-1.5 font-mono text-xs text-slate-700">{preview(h.action === 'delete' ? h.old_data : h.new_data)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <Pagination page={history.data.meta.current_page} lastPage={history.data.meta.last_page} total={history.data.meta.total} onChange={(p) => setFilter('page', p)} />
                </div>
            )}
        </div>
    );
}
