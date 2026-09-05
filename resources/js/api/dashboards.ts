import { api } from './client';
import type { Wrapped } from './types';

export type ParamScalar = string | number | boolean;

export type ParamType = 'number' | 'text' | 'boolean' | 'select' | 'range' | 'date' | 'color';

export interface ParamOption {
    value: ParamScalar;
    label: string;
}

export interface ParamDefinition {
    id: string;
    label: string;
    type: ParamType;
    default: ParamScalar;
    min?: number | string;
    max?: number | string;
    step?: number;
    unit?: string;
    maxLength?: number;
    options?: ParamOption[];
}

export interface Manifest {
    id: string;
    version: string;
    title: string;
    params: ParamDefinition[];
}

export interface ResolvedParam {
    value: ParamScalar;
    source: 'user' | 'base' | 'default';
    has_override: boolean;
    stale: boolean;
}

export interface DashboardSummary {
    id: string;
    slug: string;
    title: string;
    version: string;
    is_published: boolean;
    param_count: number;
    created_by?: { id: string; name: string };
    created_at: string | null;
    updated_at: string | null;
}

export interface DashboardDetail {
    id: string;
    slug: string;
    title: string;
    version: string;
    is_published: boolean;
    manifest: Manifest;
    html: string;
    params: Record<string, ResolvedParam>;
    security: { cdn_allowlist: string[]; csp: string };
    created_at: string | null;
    updated_at: string | null;
}

export function listDashboards(): Promise<DashboardSummary[]> {
    return api<Wrapped<DashboardSummary[]>>('GET', '/api/dashboards').then((r) => r.data);
}

export function getDashboard(id: string): Promise<DashboardDetail> {
    return api<Wrapped<DashboardDetail>>('GET', `/api/dashboards/${id}`).then((r) => r.data);
}

/* ---------- Administración ---------- */

export interface ManifestDiff {
    added: { id: string; type: string; label: string }[];
    removed: { id: string; type: string; label: string }[];
    type_changed: { id: string; from: string; to: string }[];
    modified: { id: string; fields: string[] }[];
    unchanged: number;
    warnings: string[];
}

export interface PreviewResult {
    valid: boolean;
    problems: { rule: number; path: string; message: string }[];
    manifest: {
        id: string | null;
        title: string | null;
        version: string | null;
        params: { id: string | null; label: string | null; type: string | null; default: unknown }[];
    } | null;
    existing: DashboardSummary | null;
    diff: ManifestDiff | null;
}

export function adminListDashboards(): Promise<DashboardSummary[]> {
    return api<Wrapped<DashboardSummary[]>>('GET', '/api/admin/dashboards').then((r) => r.data);
}

export function previewDashboard(html: string): Promise<PreviewResult> {
    return api('POST', '/api/admin/dashboards/preview', { html });
}

export function createDashboard(html: string, isPublished: boolean): Promise<DashboardSummary> {
    return api<Wrapped<DashboardSummary>>('POST', '/api/admin/dashboards', { html, is_published: isPublished }).then((r) => r.data);
}

export function updateDashboard(
    id: string,
    changes: { html?: string; is_published?: boolean },
): Promise<{ data: DashboardSummary; diff: ManifestDiff | null }> {
    return api('PUT', `/api/admin/dashboards/${id}`, changes);
}

export function deleteDashboard(id: string): Promise<void> {
    return api<void>('DELETE', `/api/admin/dashboards/${id}`);
}
