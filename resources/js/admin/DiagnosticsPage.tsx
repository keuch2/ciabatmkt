import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { dashboardHtmlPath, getDiagnostics, type DiagnosticFinding } from '@/api/admin';
import { withBase } from '@/app/basePath';
import { useRequest } from '@/app/useRequest';
import { Alert } from '@/ui/Alert';
import { Button } from '@/ui/Button';
import { formatDateTime } from '@/ui/formatValue';
import { PageHeader } from '@/ui/PageHeader';
import { Spinner } from '@/ui/Spinner';

const SEVERITY: Record<DiagnosticFinding['severity'], { label: string; className: string }> = {
    error: { label: 'Error', className: 'bg-red-100 text-red-800' },
    warning: { label: 'Atención', className: 'bg-amber-100 text-amber-800' },
    info: { label: 'Nota', className: 'bg-slate-200 text-slate-700' },
};

const STATUS = {
    ok: { tone: 'success' as const, text: 'Sin problemas detectados. El dashboard guarda y lee datos con normalidad.' },
    warning: { tone: 'info' as const, text: 'Hay señales que conviene revisar; el dashboard funciona pero algo puede estar fallando para algún usuario.' },
    error: { tone: 'error' as const, text: 'Hay problemas que impiden guardar datos o que ya rechazaron escrituras de usuarios.' },
};

function kb(bytes: number): string {
    return `${Math.round(bytes / 1024)} KB`;
}

/**
 * Chequeo de un dashboard sin tocar código: validador, colecciones, tamaños, escrituras
 * rechazadas y guardados sin efecto. El informe se copia y se pega en el prompt de corrección.
 */
export function DiagnosticsPage() {
    const { id = '' } = useParams();
    const { data, error, loading, reload } = useRequest(() => getDiagnostics(id), [id]);
    const [copied, setCopied] = useState(false);

    async function copyReport() {
        if (!data) return;
        try {
            await navigator.clipboard.writeText(data.report);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2500);
        } catch {
            window.prompt('Copiá el informe:', data.report);
        }
    }

    if (loading) return <Spinner />;
    if (error || !data) return <Alert tone="error">{error ?? 'Error'}</Alert>;

    const { summary } = data;

    return (
        <div className="max-w-5xl">
            <PageHeader
                title={`Diagnóstico · ${summary.dashboard.title}`}
                description={`Versión ${summary.dashboard.version} · id ${summary.dashboard.slug} · últimos ${summary.days} días`}
                actions={
                    <>
                        <Button variant="secondary" onClick={() => reload()}>
                            Volver a analizar
                        </Button>
                        <Button onClick={() => void copyReport()}>{copied ? 'Informe copiado' : 'Copiar informe'}</Button>
                        <Link to="/admin/dashboards" className="text-sm text-slate-600 underline-offset-2 hover:underline">
                            Volver
                        </Link>
                    </>
                }
            />

            <div className="mb-4">
                <Alert tone={STATUS[data.status].tone}>{STATUS[data.status].text}</Alert>
            </div>

            <section className="mb-4 rounded border border-slate-200 bg-white">
                <div className="border-b border-slate-200 px-3 py-2 text-sm font-semibold text-slate-900">Hallazgos</div>
                {data.findings.length === 0 && <p className="px-3 py-4 text-sm text-slate-500">Ninguno.</p>}
                <ul className="divide-y divide-slate-100">
                    {data.findings.map((f, i) => (
                        <li key={i} className="px-3 py-2.5">
                            <div className="flex items-start gap-2">
                                <span className={`mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${SEVERITY[f.severity].className}`}>{SEVERITY[f.severity].label}</span>
                                <div className="min-w-0">
                                    <p className="text-sm text-slate-900">
                                        <span className="mr-1 text-xs uppercase tracking-wide text-slate-400">{f.area}</span>
                                        {f.message}
                                    </p>
                                    <p className="mt-0.5 text-xs text-slate-600">{f.action}</p>
                                </div>
                            </div>
                        </li>
                    ))}
                </ul>
            </section>

            <section className="mb-4 overflow-x-auto rounded border border-slate-200 bg-white">
                <div className="border-b border-slate-200 px-3 py-2 text-sm font-semibold text-slate-900">Colecciones</div>
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th className="px-3 py-2">Colección</th>
                            <th className="px-3 py-2 text-right">Registros</th>
                            <th className="px-3 py-2 text-right">Registro más grande</th>
                            <th className="px-3 py-2 text-right">Tope</th>
                            <th className="px-3 py-2">Último guardado</th>
                            <th className="px-3 py-2">Usada en el código</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {summary.collections.map((c) => {
                            const pct = c.cap_bytes ? Math.round((c.max_bytes * 100) / c.cap_bytes) : 0;
                            return (
                                <tr key={c.id}>
                                    <td className="px-3 py-1.5">
                                        <span className="font-mono text-xs">{c.id}</span>
                                        <span className="block text-xs text-slate-500">{c.label}</span>
                                    </td>
                                    <td className="px-3 py-1.5 text-right tabular-nums">{c.records}</td>
                                    <td className={`px-3 py-1.5 text-right tabular-nums ${pct >= 95 ? 'text-red-700' : pct >= 80 ? 'text-amber-700' : ''}`}>
                                        {c.max_bytes ? `${kb(c.max_bytes)} (${pct}%)` : '—'}
                                    </td>
                                    <td className="px-3 py-1.5 text-right tabular-nums text-slate-500">{kb(c.cap_bytes)}</td>
                                    <td className="px-3 py-1.5 text-xs text-slate-500">{formatDateTime(c.last_write) || '—'}</td>
                                    <td className="px-3 py-1.5 text-xs">{c.used_in_code === null ? 'no se pudo determinar' : c.used_in_code ? 'sí' : <span className="text-amber-700">no</span>}</td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </section>

            <section className="mb-4 rounded border border-slate-200 bg-white">
                <div className="flex items-center justify-between border-b border-slate-200 px-3 py-2 text-sm">
                    <span className="font-semibold text-slate-900">Escrituras rechazadas recientes</span>
                    <span className="text-xs text-slate-500">
                        {summary.writes_last_days} guardados correctos en {summary.days} días · último {formatDateTime(summary.last_write) || '—'}
                    </span>
                </div>
                {summary.recent_failures.length === 0 && <p className="px-3 py-4 text-sm text-slate-500">Ninguna en los últimos {summary.days} días.</p>}
                {summary.recent_failures.length > 0 && (
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-3 py-2">Cuándo</th>
                                <th className="px-3 py-2">Tipo</th>
                                <th className="px-3 py-2">Colección / registro</th>
                                <th className="px-3 py-2">Usuario</th>
                                <th className="px-3 py-2">Motivo</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {summary.recent_failures.map((f, i) => (
                                <tr key={i} className="align-top">
                                    <td className="whitespace-nowrap px-3 py-1.5 text-xs text-slate-500">{formatDateTime(f.at)}</td>
                                    <td className="px-3 py-1.5 text-xs">{f.code === 'conflict' ? 'conflicto' : f.code === 'forbidden' ? 'sin permiso' : 'rechazada'}</td>
                                    <td className="px-3 py-1.5 font-mono text-xs">
                                        {f.collection}
                                        {f.record_id ? `/${f.record_id}` : ''}
                                    </td>
                                    <td className="px-3 py-1.5 text-xs">{f.user ?? '—'}</td>
                                    <td className="px-3 py-1.5 text-xs text-slate-800">{f.message}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </section>

            <section className="rounded border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700">
                <p className="font-medium text-slate-900">Cómo corregirlo sin programar</p>
                <ol className="mt-1 list-decimal space-y-1 pl-5">
                    <li>Copiá el informe con el botón de arriba.</li>
                    <li>
                        Descargá el archivo vigente:{' '}
                        <a href={withBase(dashboardHtmlPath(id))} download className="underline">
                            {summary.dashboard.slug}-v{summary.dashboard.version}.html
                        </a>
                        .
                    </li>
                    <li>
                        En <Link to="/admin/docs" className="underline">Docs</Link>, pestaña "Prompt de corrección": pegá el bloque, el informe y el archivo en el asistente de IA.
                    </li>
                    <li>Actualizá el dashboard con el archivo corregido y volvé a analizar.</li>
                </ol>
            </section>
        </div>
    );
}
