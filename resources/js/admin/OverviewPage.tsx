import { Link, useParams } from 'react-router-dom';
import { getOverview, type Overview } from '@/api/admin';
import type { ResolvedParam } from '@/api/dashboards';
import { useRequest } from '@/app/useRequest';
import { Alert } from '@/ui/Alert';
import { formatScalar } from '@/ui/formatValue';

type OverviewParam = Overview['params'][number];

function cell(p: OverviewParam, r: ResolvedParam | undefined): string {
    if (!r) return '—';
    if (p.type === 'select' && p.options) return p.options.find((o) => o.value === r.value)?.label ?? String(r.value);
    return formatScalar(r.value, p.unit);
}
import { PageHeader } from '@/ui/PageHeader';
import { Spinner } from '@/ui/Spinner';

const CELL: Record<ResolvedParam['source'], string> = {
    user: 'bg-blue-50 text-blue-900 font-medium',
    base: 'text-slate-800',
    default: 'text-slate-400',
};

/** Matriz usuarios × parámetros con el valor efectivo y su origen. */
export function OverviewPage() {
    const { id = '' } = useParams();
    const { data, error, loading } = useRequest(() => getOverview(id), [id]);

    if (loading) return <Spinner />;
    if (error || !data) return <Alert tone="error">{error ?? 'Error'}</Alert>;

    const withOverrides = data.users.filter((u) => u.override_count > 0).length;

    return (
        <div>
            <PageHeader
                title={`Escenarios · ${data.dashboard.title}`}
                description={`Versión ${data.dashboard.version} · ${withOverrides} de ${data.users.length} usuarios activos con valores propios.`}
                actions={
                    <>
                        <Link to={`/admin/dashboards/${id}/base`} className="text-sm text-slate-600 underline-offset-2 hover:underline">
                            Editar valores base
                        </Link>
                        <Link to="/admin/dashboards" className="text-sm text-slate-600 underline-offset-2 hover:underline">
                            Volver
                        </Link>
                    </>
                }
            />

            <p className="mb-2 flex gap-4 text-xs text-slate-500">
                <span>
                    <span className="mr-1 inline-block h-3 w-3 rounded bg-blue-50 align-middle ring-1 ring-blue-200" /> valor propio del usuario
                </span>
                <span>
                    <span className="mr-1 inline-block h-3 w-3 rounded bg-white align-middle ring-1 ring-slate-300" /> valor base
                </span>
                <span className="text-slate-400">gris: default del manifiesto</span>
            </p>

            <div className="overflow-x-auto rounded border border-slate-200 bg-white">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th className="sticky left-0 bg-slate-50 px-3 py-2">Usuario</th>
                            {data.params.map((p) => (
                                <th key={p.id} className="px-3 py-2" title={p.id}>
                                    {p.label}
                                </th>
                            ))}
                            <th className="px-3 py-2 text-right">Propios</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        <tr className="bg-slate-50/60">
                            <td className="sticky left-0 bg-slate-50 px-3 py-2 font-semibold text-slate-900">Valor base</td>
                            {data.params.map((p) => {
                                const r = data.base[p.id];
                                return (
                                    <td key={p.id} className={`px-3 py-2 font-mono text-xs ${r ? CELL[r.source] : ''}`} title={r?.source}>
                                        {cell(p, r)}
                                    </td>
                                );
                            })}
                            <td className="px-3 py-2 text-right text-xs text-slate-500">
                                {Object.values(data.base).filter((r) => r.has_override).length}
                            </td>
                        </tr>
                        {data.users.map((row) => (
                            <tr key={row.user.id} className="hover:bg-slate-50">
                                <td className="sticky left-0 bg-white px-3 py-2">
                                    <p className="text-slate-900">{row.user.name}</p>
                                    <p className="text-xs text-slate-500">{row.user.email}</p>
                                </td>
                                {data.params.map((p) => {
                                    const r = row.params[p.id];
                                    return (
                                        <td key={p.id} className={`px-3 py-2 font-mono text-xs ${r ? CELL[r.source] : ''}`} title={r ? (r.stale ? 'valor guardado obsoleto' : r.source) : ''}>
                                            {cell(p, r)}
                                            {r?.stale && <span className="ml-1 text-amber-700">!</span>}
                                        </td>
                                    );
                                })}
                                <td className="px-3 py-2 text-right text-xs text-slate-600">{row.override_count}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
