import type { HistoryEntry } from '@/api/params';
import { formatDateTime, formatScalar } from '@/ui/formatValue';

const ACTION: Record<HistoryEntry['action'], { label: string; className: string }> = {
    insert: { label: 'Definió', className: 'bg-green-100 text-green-800' },
    update: { label: 'Cambió', className: 'bg-slate-200 text-slate-700' },
    delete: { label: 'Restableció', className: 'bg-amber-100 text-amber-800' },
};

export function HistoryTable({ entries, showUser = true }: { entries: HistoryEntry[]; showUser?: boolean }) {
    if (entries.length === 0) {
        return <p className="px-3 py-8 text-center text-sm text-slate-500">No hay cambios registrados con estos filtros.</p>;
    }

    return (
        <table className="w-full text-sm">
            <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th className="px-3 py-2">Fecha</th>
                    <th className="px-3 py-2">Parámetro</th>
                    {showUser && <th className="px-3 py-2">Nivel</th>}
                    <th className="px-3 py-2">Acción</th>
                    <th className="px-3 py-2">Anterior</th>
                    <th className="px-3 py-2">Nuevo</th>
                    {showUser && <th className="px-3 py-2">Hecho por</th>}
                </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
                {entries.map((e) => (
                    <tr key={e.id} className="hover:bg-slate-50">
                        <td className="whitespace-nowrap px-3 py-1.5 text-xs text-slate-500">{formatDateTime(e.changed_at)}</td>
                        <td className="px-3 py-1.5">
                            <span className="text-slate-900">{e.label ?? e.param_id}</span>
                            {e.label === null && (
                                <span className="ml-1 rounded bg-slate-100 px-1 text-[10px] text-slate-500" title="Ya no existe en el manifiesto vigente">
                                    huérfano
                                </span>
                            )}
                        </td>
                        {showUser && (
                            <td className="px-3 py-1.5 text-xs">
                                {e.scope === 'base' ? <span className="rounded bg-slate-800 px-1.5 py-0.5 text-white">base</span> : (e.user?.name ?? '—')}
                            </td>
                        )}
                        <td className="px-3 py-1.5">
                            <span className={`rounded px-1.5 py-0.5 text-xs ${ACTION[e.action].className}`}>{ACTION[e.action].label}</span>
                        </td>
                        <td className="px-3 py-1.5 font-mono text-xs text-slate-500">{formatScalar(e.old_value)}</td>
                        <td className="px-3 py-1.5 font-mono text-xs text-slate-900">{formatScalar(e.new_value)}</td>
                        {showUser && <td className="px-3 py-1.5 text-xs text-slate-600">{e.changed_by?.name ?? '—'}</td>}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

export function Pagination({ page, lastPage, total, onChange }: { page: number; lastPage: number; total: number; onChange: (p: number) => void }) {
    if (lastPage <= 1) return <p className="px-3 py-2 text-xs text-slate-500">{total} registro{total === 1 ? '' : 's'}</p>;
    return (
        <div className="flex items-center justify-between px-3 py-2 text-xs text-slate-600">
            <span>
                Página {page} de {lastPage} · {total} registros
            </span>
            <div className="flex gap-1">
                <button type="button" disabled={page <= 1} onClick={() => onChange(page - 1)} className="rounded border border-slate-300 px-2 py-0.5 disabled:opacity-40">
                    Anterior
                </button>
                <button type="button" disabled={page >= lastPage} onClick={() => onChange(page + 1)} className="rounded border border-slate-300 px-2 py-0.5 disabled:opacity-40">
                    Siguiente
                </button>
            </div>
        </div>
    );
}
