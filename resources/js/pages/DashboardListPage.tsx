import { Link } from 'react-router-dom';
import { listDashboards } from '@/api/dashboards';
import { useRequest } from '@/app/useRequest';
import { Alert } from '@/ui/Alert';
import { PageHeader } from '@/ui/PageHeader';
import { Spinner } from '@/ui/Spinner';

export function DashboardListPage() {
    const { data, error, loading } = useRequest(listDashboards, []);

    return (
        <div>
            <PageHeader title="Dashboards" description="Dashboards publicados disponibles para tu usuario." />

            {loading && <Spinner />}
            {error && <Alert tone="error">{error}</Alert>}

            {data && data.length === 0 && (
                <div className="rounded border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">
                    Todavía no hay dashboards publicados.
                </div>
            )}

            {data && data.length > 0 && (
                <ul className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {data.map((d) => (
                        <li key={d.id}>
                            <Link
                                to={`/dashboards/${d.id}`}
                                className="block rounded border border-slate-200 bg-white px-4 py-3 transition-colors hover:border-slate-400"
                            >
                                <p className="font-medium text-slate-900">{d.title}</p>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    v{d.version} · {d.param_count} {d.param_count === 1 ? 'parámetro' : 'parámetros'}
                                </p>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
