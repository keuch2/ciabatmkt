import type { ManifestDiff } from '@/api/dashboards';

export function ManifestDiffSummary({ diff }: { diff: ManifestDiff }) {
    const rows: { label: string; items: string[]; tone: string }[] = [
        { label: 'Agregados', items: diff.added.map((p) => `${p.id} (${p.type})`), tone: 'text-green-800' },
        { label: 'Eliminados', items: diff.removed.map((p) => `${p.id} (${p.type})`), tone: 'text-red-800' },
        { label: 'Cambian de tipo', items: diff.type_changed.map((p) => `${p.id}: ${p.from} → ${p.to}`), tone: 'text-amber-800' },
        { label: 'Modificados', items: diff.modified.map((p) => `${p.id} (${p.fields.join(', ')})`), tone: 'text-slate-700' },
    ];

    return (
        <div className="space-y-2 text-sm">
            <dl className="grid grid-cols-[140px_1fr] gap-y-1">
                {rows.map((r) => (
                    <div key={r.label} className="contents">
                        <dt className="text-xs uppercase tracking-wide text-slate-500">{r.label}</dt>
                        <dd className={`font-mono text-xs ${r.tone}`}>{r.items.length ? r.items.join(', ') : '—'}</dd>
                    </div>
                ))}
                <dt className="text-xs uppercase tracking-wide text-slate-500">Sin cambios</dt>
                <dd className="font-mono text-xs text-slate-700">{diff.unchanged}</dd>
            </dl>
            {diff.warnings.length > 0 && (
                <ul className="list-disc space-y-1 rounded border border-amber-200 bg-amber-50 py-2 pl-6 pr-3 text-xs text-amber-900">
                    {diff.warnings.map((w) => (
                        <li key={w}>{w}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}
