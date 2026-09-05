import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { getDashboard, type ParamDefinition, type ParamScalar, type ResolvedParam } from '@/api/dashboards';
import { useRequest } from '@/app/useRequest';
import { DashboardFrame } from '@/dashboard/DashboardFrame';
import { Alert } from '@/ui/Alert';
import { PageHeader } from '@/ui/PageHeader';
import { Spinner } from '@/ui/Spinner';

const SOURCE_LABEL: Record<ResolvedParam['source'], string> = {
    user: 'tu valor',
    base: 'valor base',
    default: 'default',
};

function formatValue(def: ParamDefinition, value: ParamScalar): string {
    if (typeof value === 'boolean') return value ? 'Sí' : 'No';
    if (def.type === 'select') return def.options?.find((o) => o.value === value)?.label ?? String(value);
    if (typeof value === 'number') return `${value.toLocaleString('es-PY')}${def.unit ? ` ${def.unit}` : ''}`;
    return String(value);
}

export function DashboardPage() {
    const { id = '' } = useParams();
    const { data, error, status, loading } = useRequest(() => getDashboard(id), [id]);

    // Valores efectivos: los resueltos por el servidor más los cambios locales de esta sesión.
    // Se derivan de forma síncrona para que el iframe se construya ya con los valores correctos.
    // Semana 3: el panel de controles edita y persiste estos cambios.
    const resolvedValues = useMemo(
        () => (data ? Object.fromEntries(Object.entries(data.params).map(([k, v]) => [k, v.value])) : {}),
        [data],
    );
    const [localChanges, setLocalChanges] = useState<Record<string, ParamScalar>>({});
    const [frameErrors, setFrameErrors] = useState<string[]>([]);
    const values = useMemo(() => ({ ...resolvedValues, ...localChanges }), [resolvedValues, localChanges]);

    useEffect(() => {
        setLocalChanges({});
        setFrameErrors([]);
    }, [data]);

    const handleParamChange = useCallback((paramId: string, value: ParamScalar) => {
        setLocalChanges((v) => ({ ...v, [paramId]: value }));
    }, []);
    const handleError = useCallback((message: string) => {
        setFrameErrors((list) => (list.includes(message) ? list : [...list, message]).slice(-5));
    }, []);

    const stale = useMemo(
        () => (data ? Object.entries(data.params).filter(([, p]) => p.stale).map(([k]) => k) : []),
        [data],
    );

    if (loading) return <Spinner />;
    if (error) {
        return (
            <div className="space-y-3">
                <Alert tone="error">{status === 404 ? 'Este dashboard no existe o no está publicado.' : error}</Alert>
                <Link to="/" className="text-sm text-slate-700 underline-offset-2 hover:underline">
                    Volver al listado
                </Link>
            </div>
        );
    }
    if (!data) return null;

    return (
        <div className="flex h-full flex-col">
            <PageHeader
                title={data.title}
                description={`Versión ${data.version}`}
                actions={
                    <Link to="/" className="text-sm text-slate-600 underline-offset-2 hover:underline">
                        Volver
                    </Link>
                }
            />

            {stale.length > 0 && (
                <div className="mb-3">
                    <Alert tone="info">
                        Algunos de tus valores guardados ya no son válidos para esta versión ({stale.join(', ')}) y se muestran los
                        valores por defecto.
                    </Alert>
                </div>
            )}
            {frameErrors.length > 0 && (
                <div className="mb-3">
                    <Alert tone="error">
                        <p className="font-medium">El dashboard reportó errores:</p>
                        <ul className="mt-1 list-disc pl-4 font-mono text-xs">
                            {frameErrors.map((e) => (
                                <li key={e}>{e}</li>
                            ))}
                        </ul>
                    </Alert>
                </div>
            )}

            <div className="grid min-w-0 grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_300px]">
                <DashboardFrame
                    html={data.html}
                    csp={data.security.csp}
                    params={values}
                    onParamChange={handleParamChange}
                    onError={handleError}
                />

                <aside className="rounded border border-slate-200 bg-white">
                    <div className="border-b border-slate-200 px-3 py-2">
                        <p className="text-sm font-semibold text-slate-900">Parámetros</p>
                        <p className="text-xs text-slate-500">Los controles de edición llegan en la siguiente etapa.</p>
                    </div>
                    <dl className="divide-y divide-slate-100">
                        {data.manifest.params.map((def) => {
                            const resolved = data.params[def.id];
                            return (
                                <div key={def.id} className="px-3 py-2">
                                    <dt className="flex items-center justify-between gap-2 text-xs text-slate-500">
                                        <span>{def.label}</span>
                                        {resolved && (
                                            <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] uppercase tracking-wide">
                                                {SOURCE_LABEL[resolved.source]}
                                            </span>
                                        )}
                                    </dt>
                                    <dd className="mt-0.5 flex items-center gap-2 text-sm text-slate-900">
                                        {def.type === 'color' && (
                                            <span
                                                className="inline-block h-3.5 w-3.5 rounded border border-slate-300"
                                                style={{ background: String(values[def.id] ?? def.default) }}
                                            />
                                        )}
                                        {formatValue(def, values[def.id] ?? def.default)}
                                    </dd>
                                </div>
                            );
                        })}
                    </dl>
                </aside>
            </div>
        </div>
    );
}
