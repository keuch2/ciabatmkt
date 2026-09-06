import { api } from './client';
import type { ParamScalar, ResolvedParam } from './dashboards';

export type ParamScope = 'user' | 'base';

export interface ResolvedParamWithId extends ResolvedParam {
    param_id: string;
}

export function saveParam(dashboardId: string, paramId: string, value: ParamScalar, scope: ParamScope): Promise<ResolvedParamWithId> {
    return api<{ data: ResolvedParamWithId }>('PUT', `/api/dashboards/${dashboardId}/params/${encodeURIComponent(paramId)}`, {
        value,
        scope,
    }).then((r) => r.data);
}

export function resetParam(dashboardId: string, paramId: string, scope: ParamScope): Promise<ResolvedParamWithId> {
    return api<{ data: ResolvedParamWithId }>('DELETE', `/api/dashboards/${dashboardId}/params/${encodeURIComponent(paramId)}?scope=${scope}`).then(
        (r) => r.data,
    );
}

export function resetAllParams(dashboardId: string, scope: ParamScope): Promise<{ removed: number; data: Record<string, ResolvedParam> }> {
    return api('DELETE', `/api/dashboards/${dashboardId}/params?scope=${scope}`);
}

export interface HistoryEntry {
    id: string;
    param_id: string;
    label: string | null;
    scope: ParamScope;
    user: { id: string; name: string } | null;
    action: 'insert' | 'update' | 'delete';
    old_value: ParamScalar | null;
    new_value: ParamScalar | null;
    changed_by: { id: string; name: string } | null;
    changed_at: string | null;
}

export interface HistoryPage {
    data: HistoryEntry[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
}

export function getMyHistory(dashboardId: string, params: { param_id?: string; page?: number } = {}): Promise<HistoryPage> {
    const query = new URLSearchParams();
    if (params.param_id) query.set('param_id', params.param_id);
    if (params.page) query.set('page', String(params.page));
    const qs = query.toString();
    return api('GET', `/api/dashboards/${dashboardId}/history${qs ? `?${qs}` : ''}`);
}
