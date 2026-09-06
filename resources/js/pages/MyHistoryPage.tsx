import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { getDashboard } from '@/api/dashboards';
import { getMyHistory } from '@/api/params';
import { useRequest } from '@/app/useRequest';
import { HistoryTable, Pagination } from '@/admin/HistoryTable';
import { Alert } from '@/ui/Alert';
import { PageHeader } from '@/ui/PageHeader';
import { Select } from '@/ui/Select';
import { Spinner } from '@/ui/Spinner';

/** Historial propio del usuario para un dashboard. */
export function MyHistoryPage() {
    const { id = '' } = useParams();
    const [paramId, setParamId] = useState('');
    const [page, setPage] = useState(1);
    const detail = useRequest(() => getDashboard(id), [id]);
    const history = useRequest(() => getMyHistory(id, { param_id: paramId || undefined, page }), [id, paramId, page]);

    if (detail.loading) return <Spinner />;
    if (detail.error || !detail.data) return <Alert tone="error">{detail.error ?? 'Error'}</Alert>;

    return (
        <div>
            <PageHeader
                title={`Mis cambios · ${detail.data.title}`}
                description="Cada valor que definiste o restableciste en este dashboard."
                actions={
                    <Link to={`/dashboards/${id}`} className="text-sm text-slate-600 underline-offset-2 hover:underline">
                        Volver al dashboard
                    </Link>
                }
            />
            <div className="mb-3 max-w-xs">
                <Select
                    value={paramId}
                    onChange={(e) => {
                        setParamId(e.target.value);
                        setPage(1);
                    }}
                >
                    <option value="">Todos los parámetros</option>
                    {detail.data.manifest.params.map((p) => (
                        <option key={p.id} value={p.id}>
                            {p.label}
                        </option>
                    ))}
                </Select>
            </div>
            {history.loading && <Spinner />}
            {history.error && <Alert tone="error">{history.error}</Alert>}
            {history.data && (
                <div className="overflow-x-auto rounded border border-slate-200 bg-white">
                    <HistoryTable entries={history.data.data} showUser={false} />
                    <Pagination page={history.data.meta.current_page} lastPage={history.data.meta.last_page} total={history.data.meta.total} onChange={setPage} />
                </div>
            )}
        </div>
    );
}
