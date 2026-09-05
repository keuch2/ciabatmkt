import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import * as authApi from '@/api/auth';
import type { User } from '@/api/types';

interface AuthContextValue {
    user: User | null;
    /** 'loading' mientras se consulta /api/auth/me al cargar la página. */
    status: 'loading' | 'ready';
    login: (email: string, password: string) => Promise<User>;
    logout: () => Promise<void>;
    refresh: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [status, setStatus] = useState<'loading' | 'ready'>('loading');

    const refresh = useCallback(async () => {
        try {
            setUser(await authApi.me());
        } catch {
            setUser(null);
        }
    }, []);

    useEffect(() => {
        refresh().finally(() => setStatus('ready'));
    }, [refresh]);

    const login = useCallback(async (email: string, password: string) => {
        const logged = await authApi.login(email, password);
        setUser(logged);
        return logged;
    }, []);

    const logout = useCallback(async () => {
        try {
            await authApi.logout();
        } finally {
            setUser(null);
        }
    }, []);

    const value = useMemo(() => ({ user, status, login, logout, refresh }), [user, status, login, logout, refresh]);

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
    const ctx = useContext(AuthContext);
    if (!ctx) throw new Error('useAuth debe usarse dentro de <AuthProvider>');
    return ctx;
}
