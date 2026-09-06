import { api } from './client';
import type { ParamScalar, ParamType, ResolvedParam } from './dashboards';
import type { HistoryPage } from './params';
import type { User, UserRole, Wrapped } from './types';

/* ---------- Usuarios ---------- */

export interface UserPayload {
    name: string;
    email: string;
    password?: string;
    role: UserRole;
    is_active: boolean;
}

export function listUsers(): Promise<User[]> {
    return api<Wrapped<User[]>>('GET', '/api/admin/users').then((r) => r.data);
}

export function createUser(payload: UserPayload): Promise<User> {
    return api<Wrapped<User>>('POST', '/api/admin/users', payload).then((r) => r.data);
}

export function updateUser(id: string, payload: Partial<UserPayload>): Promise<User> {
    return api<Wrapped<User>>('PUT', `/api/admin/users/${id}`, payload).then((r) => r.data);
}

/* ---------- Escenarios ---------- */

export interface Overview {
    dashboard: { id: string; slug: string; title: string; version: string };
    params: { id: string; label: string; type: ParamType; unit: string | null; default: ParamScalar; options: { value: ParamScalar; label: string }[] | null }[];
    base: Record<string, ResolvedParam>;
    users: {
        user: { id: string; name: string; email: string; role: UserRole };
        params: Record<string, ResolvedParam>;
        override_count: number;
    }[];
}

export function getOverview(dashboardId: string): Promise<Overview> {
    return api('GET', `/api/admin/dashboards/${dashboardId}/overview`);
}

/* ---------- Historial completo ---------- */

export interface HistoryFilters {
    param_id?: string;
    user_id?: string;
    scope?: 'user' | 'base' | '';
    action?: 'insert' | 'update' | 'delete' | '';
    from?: string;
    to?: string;
    page?: number;
}

export function getAdminHistory(dashboardId: string, filters: HistoryFilters): Promise<HistoryPage> {
    const query = new URLSearchParams();
    for (const [key, value] of Object.entries(filters)) {
        if (value !== undefined && value !== '' && value !== null) query.set(key, String(value));
    }
    const qs = query.toString();
    return api('GET', `/api/admin/dashboards/${dashboardId}/history${qs ? `?${qs}` : ''}`);
}
