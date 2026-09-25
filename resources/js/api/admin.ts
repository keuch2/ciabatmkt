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
    division_ids: string[];
    group_ids: string[];
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

/* ---------- Documentación ---------- */

export interface Docs {
    prompt: { intro_html: string; text: string; example_html: string };
    fix_prompt: { intro_html: string; text: string; example_html: string };
    specification_html: string;
    guide_html: string;
    cdn_allowlist: string[];
}

export function getDocs(): Promise<Docs> {
    return api('GET', '/api/admin/docs');
}

export const REFERENCE_DASHBOARD_PATH = '/api/admin/docs/dashboard-referencia.html';

/* ---------- Datos compartidos (registros) ---------- */

export interface DataSummary {
    collections: { id: string; label: string | null; max_records: number | null; records: number; last_updated_at: string | null }[];
    orphan_collections: { id: string; label: null; max_records: null; records: number; last_updated_at: null }[];
}

export function getDataSummary(dashboardId: string): Promise<DataSummary> {
    return api('GET', `/api/admin/dashboards/${dashboardId}/data`);
}

export function getAdminRecords(dashboardId: string, collection: string): Promise<import('./records').RecordList> {
    return api('GET', `/api/admin/dashboards/${dashboardId}/data/${encodeURIComponent(collection)}`);
}

export const exportPath = (dashboardId: string, collection: string) => `/api/admin/dashboards/${dashboardId}/data/${encodeURIComponent(collection)}/export`;

export interface DataHistoryEntry {
    id: string;
    collection: string;
    record_id: string;
    action: 'insert' | 'update' | 'delete';
    version: number;
    old_data: unknown;
    new_data: unknown;
    changed_by: { id: string; name: string } | null;
    changed_at: string | null;
}

export interface DataHistoryFilters {
    collection?: string;
    record_id?: string;
    user_id?: string;
    action?: 'insert' | 'update' | 'delete' | '';
    from?: string;
    to?: string;
    page?: number;
}

export function getDataHistory(dashboardId: string, filters: DataHistoryFilters): Promise<{ data: DataHistoryEntry[]; meta: { current_page: number; last_page: number; per_page: number; total: number } }> {
    const query = new URLSearchParams();
    for (const [key, value] of Object.entries(filters)) {
        if (value !== undefined && value !== '' && value !== null) query.set(key, String(value));
    }
    const qs = query.toString();
    return api('GET', `/api/admin/dashboards/${dashboardId}/data-history${qs ? `?${qs}` : ''}`);
}

/* ---------- Diagnóstico y descarga ---------- */

export interface DiagnosticFinding {
    severity: 'error' | 'warning' | 'info';
    area: string;
    message: string;
    action: string;
}

export interface Diagnostics {
    status: 'ok' | 'warning' | 'error';
    findings: DiagnosticFinding[];
    summary: {
        dashboard: { id: string; slug: string; title: string; version: string; updated_at: string | null };
        collections: { id: string; label: string; records: number; max_bytes: number; cap_bytes: number; last_write: string | null; used_in_code: boolean | null }[];
        writes_last_days: number;
        last_write: string | null;
        failures_last_days: Record<string, number>;
        recent_failures: { at: string | null; code: string; operation: string; collection: string; record_id: string | null; user: string | null; message: string; bytes: number | null }[];
        days: number;
    };
    report: string;
}

export function getDiagnostics(dashboardId: string): Promise<Diagnostics> {
    return api('GET', `/api/admin/dashboards/${dashboardId}/diagnostics`);
}

export const dashboardHtmlPath = (dashboardId: string) => `/api/admin/dashboards/${dashboardId}/html`;
