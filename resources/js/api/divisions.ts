import { api } from './client';
import type { Wrapped } from './types';

export interface Group {
    id: string;
    division_id: string;
    name: string;
    sort_order: number;
    users_count?: number;
    dashboards_count?: number;
}

export interface Division {
    id: string;
    name: string;
    sort_order: number;
    users_count?: number;
    dashboards_count?: number;
    groups: Group[];
}

export function listDivisions(): Promise<Division[]> {
    return api<Wrapped<Division[]>>('GET', '/api/admin/divisions').then((r) => r.data);
}

export function createDivision(payload: { name: string; sort_order?: number }): Promise<Division> {
    return api<Wrapped<Division>>('POST', '/api/admin/divisions', payload).then((r) => r.data);
}

export function updateDivision(id: string, payload: { name?: string; sort_order?: number }): Promise<Division> {
    return api<Wrapped<Division>>('PUT', `/api/admin/divisions/${id}`, payload).then((r) => r.data);
}

export function deleteDivision(id: string): Promise<void> {
    return api<void>('DELETE', `/api/admin/divisions/${id}`);
}

export function createGroup(divisionId: string, payload: { name: string; sort_order?: number }): Promise<Group> {
    return api<Wrapped<Group>>('POST', `/api/admin/divisions/${divisionId}/groups`, payload).then((r) => r.data);
}

export function updateGroup(divisionId: string, groupId: string, payload: { name?: string; sort_order?: number }): Promise<Group> {
    return api<Wrapped<Group>>('PUT', `/api/admin/divisions/${divisionId}/groups/${groupId}`, payload).then((r) => r.data);
}

export function deleteGroup(divisionId: string, groupId: string): Promise<void> {
    return api<void>('DELETE', `/api/admin/divisions/${divisionId}/groups/${groupId}`);
}
