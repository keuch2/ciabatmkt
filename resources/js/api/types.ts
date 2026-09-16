export type UserRole = 'super_admin' | 'user';

export interface User {
    id: string;
    name: string;
    email: string;
    role: UserRole;
    is_active: boolean;
    created_at: string | null;
    divisions?: { id: string; name: string }[];
    groups?: { id: string; name: string; division_id: string }[];
}

export interface Wrapped<T> {
    data: T;
}
