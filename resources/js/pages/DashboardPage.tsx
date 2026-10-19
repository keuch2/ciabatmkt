import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { getDashboard, type ParamScalar } from '@/api/dashboards';
import type { ParamScope } from '@/api/params';
import { useRequest } from '@/app/useRequest';
import { useAuth } from '@/auth/AuthProvider';
import { DashboardFrame } from '@/dashboard/DashboardFrame';
import { useParamState } from '@/params/useParamState';
import { Alert } from '@/ui/Alert';
import { Spinner } from '@/ui/Spinner';

/**
 * Vista de un dashboard. Con scope "user" (por defecto) cada cambio es un override personal;
 * con scope "base" (sólo super administrador) se editan los valores que ven todos.
 */
export function DashboardPage({ scope = 'user' }: { scope?: ParamScope }) {
    const { id = '' } = useParams();
    const { user } = useAuth();
    const { data, error, status, loading } = useRequest(() => getDashboard(id), [id]);

    if (loading) return <div className="p-6"><Spinner /></div>;
    if (error || !data) {
        return (
            <div className="space-y-3 p-6">
                <Alert tone="error">{status === 404 ? 'Este dashboard no existe o no está publicado.' : (error ?? 'Error')}</Alert>
                <Link to="/" className="text-sm text-slate-700 underline-offset-2 hover:underline">
                    Volver al listado
                </Link>
            </div>
        );
    }

    if (scope === 'base' && user?.role !== 'super_admin') {
        return <div className="p-6"><Alert tone="error">Sólo un super administrador puede editar valores base.</Alert></div>;
    }

    return <LoadedDashboard data={data} scope={scope} />;
}

type Detail = NonNullable<ReturnType<typeof useRequest<Awaited<ReturnType<typeof getDashboard>>>>['data']>;

function LoadedDashboard({ data, scope }: { data: Detail; scope: ParamScope }) {
    const state = useParamState(data.id, data.params, scope);
    const [frameErrors, setFrameErrors] = useState<string[]>([]);

    useEffect(() => setFrameErrors([]), [data]);

    const handleParamChange = useCallback((paramId: string, value: ParamScalar) => state.setValue(paramId, value), [state]);
    const handleError = useCallback((message: string) => {
        setFrameErrors((list) => (list.includes(message) ? list : [...list, message]).slice(-5));
    }, []);

    const stale = useMemo(() => Object.entries(state.entries).filter(([, p]) => p.stale).map(([k]) => k), [state.entries]);

    return (
        <div className="flex h-full flex-col">
            {/* Barra compacta: el dashboard ocupa todo el ancho y casi todo el alto. */}
            <div className="flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-1.5">
                <p className="min-w-0 truncate text-sm">
                    <span className="font-semibold text-slate-900">{data.title}</span>
                    <span className="ml-2 text-xs text-slate-500">
                        {`${data.description ? `${data.description} · ` : ''}Versión ${data.version}${scope === 'base' ? ' · edición de valores base' : ''}${!data.is_published ? ' · borrador' : ''}`}
                    </span>
                </p>
                <Link to={scope === 'base' ? '/admin/dashboards' : '/'} className="shrink-0 text-xs text-slate-600 underline-offset-2 hover:underline">
                    Volver
                </Link>
            </div>

            {stale.length > 0 && (
                <div className="px-4 pt-3">
                    <Alert tone="info">
                        Algunos valores guardados ya no son válidos para esta versión ({stale.join(', ')}). Se muestra el siguiente nivel; al
                        guardar un valor nuevo se reemplazan.
                    </Alert>
                </div>
            )}
            {frameErrors.length > 0 && (
                <div className="px-4 pt-3">
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

            {/* El usuario opera con la interfaz propia del dashboard: no hay panel de parámetros. */}
            <DashboardFrame
                dashboardId={data.id}
                html={data.html}
                csp={data.security.csp}
                viewer={data.viewer}
                params={state.values}
                onParamChange={handleParamChange}
                onError={handleError}
            />
        </div>
    );
}
