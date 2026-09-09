import { api } from './client';

export interface RecordEntry {
    id: string;
    data: unknown;
    version: number;
    updated_at: string | null;
    updated_by: { id: string; name: string } | null;
}

export interface RecordList {
    records: RecordEntry[];
    server_time: string;
}

export interface RecordChanges {
    changed: RecordEntry[];
    deleted: string[];
    server_time: string;
}

const base = (dashboardId: string, collection: string) => `/api/dashboards/${dashboardId}/data/${encodeURIComponent(collection)}`;

export function listRecords(dashboardId: string, collection: string): Promise<RecordList> {
    return api('GET', base(dashboardId, collection));
}

export function putRecord(dashboardId: string, collection: string, recordId: string, data: unknown, version?: number): Promise<{ record: RecordEntry }> {
    return api('PUT', `${base(dashboardId, collection)}/${encodeURIComponent(recordId)}`, { data, version: version ?? null });
}

export function removeRecord(dashboardId: string, collection: string, recordId: string): Promise<{ deleted: boolean }> {
    return api('DELETE', `${base(dashboardId, collection)}/${encodeURIComponent(recordId)}`);
}

export function seedRecords(dashboardId: string, collection: string, records: { id: string; data: unknown }[]): Promise<{ seeded: number } & RecordList> {
    return api('POST', `${base(dashboardId, collection)}/seed`, { records });
}

export function replaceRecords(dashboardId: string, collection: string, records: { id: string; data: unknown }[]): Promise<{ replaced: number } & RecordList> {
    return api('POST', `${base(dashboardId, collection)}/replace`, { records });
}

export function recordChanges(dashboardId: string, collection: string, since: string): Promise<RecordChanges> {
    return api('GET', `${base(dashboardId, collection)}/changes?since=${encodeURIComponent(since)}`);
}
