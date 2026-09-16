import { api } from './client';
import type { Wrapped } from './types';

export interface MenuDashboard {
    id: string;
    slug: string;
    title: string;
    icon: string | null;
    is_published: boolean;
}

export interface MenuGroup {
    id: string;
    name: string;
    dashboards: MenuDashboard[];
}

export interface MenuDivision {
    id: string;
    name: string;
    dashboards: MenuDashboard[];
    groups: MenuGroup[];
}

export interface Menu {
    divisions: MenuDivision[];
    company: MenuDashboard[];
    unassigned: MenuDashboard[];
}

export function getMenu(): Promise<Menu> {
    return api<Wrapped<Menu>>('GET', '/api/menu').then((r) => r.data);
}
