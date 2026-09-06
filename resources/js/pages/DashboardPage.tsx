import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { getDashboard, type ParamScalar } from '@/api/dashboards';
import type { ParamScope } from '@/api/params';
import { useRequest } from '@/app/useRequest';
import { useAuth } from '@/auth/AuthProvider';
import { DashboardFrame } from '@/dashboard/DashboardFrame';
import { ParamPanel } from '@/params/ParamPanel';
import { useParamState } from '@/params/useParamState';
import { Alert } from '@/ui/Alert';
import { PageHeader } from '@/ui/PageHeader';
import { Spinner } from '@/ui/Spinner';

/**
 * Vista de un dashboard. Con scope "user" (por defecto) cada cambio es un override personal;
 * con scope "base" (sólo super administrador) se editan los valores que ven todos.
 */
export function DashboardPage({ scope = 'user' }: { scope?: ParamScope }) {
    const { id = '' } = useParams();
    const { user } = useAuth();
    const { data, error, status, loading } = useRequest(() => getDashboard(id), [id]);

    if (loading) return <Spinner />;
    if (error || !data) {
        return (
            <div className="space-y-3">
                <Alert tone="error">{status === 404 ? 'Este dashboard no existe o no está publicado.' : (error ?? 'Error')}</Alert>
                <Link to="/" className="text-sm text-slate-700 underline-offset-2 hover:underline">
                    Volver al listado
                </Link>
            </div>
        );
    }

    if (scope === 'base' && user?.role !== 'super_admin') {
        return <Alert tone="error">Sólo un super administrador puede editar valores base.</Alert>;
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
            <PageHeader
                title={data.title}
                description={`Versión ${data.version}${scope === 'base' ? ' · edición de valores base' : ''}${!data.is_published ? ' · borrador' : ''}`}
                actions={
                    <>
                        {scope === 'user' && (
                            <Link to={`/dashboards//history`} className="text-sm text-slate-600 underline-offset-2 hover:underline">
                                Mis cambios
                            </Link>
                        )}
                        <Link to={scope === 'base' ? '/admin/dashboards' : '/'} className="text-sm text-slate-600 underline-offset-2 hover:underline">
                            Volver
                        </Link>
                    </>
                }
            />

            {stale.length > 0 && (
                <div className="mb-3">
                    <Alert tone="info">
                        Algunos valores guardados ya no son válidos para esta versión ({stale.join(', ')}). Se muestra el siguiente nivel; al
                        guardar un valor nuevo se reemplazan.
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

            <div className="grid min-w-0 grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
                <DashboardFrame html={data.html} csp={data.security.csp} params={state.values} onParamChange={handleParamChange} onError={handleError} />
                <ParamPanel definitions={data.manifest.params} state={state} scope={scope} />
            </div>
        </div>
    );
}
