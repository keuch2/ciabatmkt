import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';
import { ApiError } from '@/api/client';
import { getMenu, type Menu } from '@/api/menu';
import { useAuth } from '@/auth/AuthProvider';

interface MenuContextValue {
    menu: Menu | null;
    loading: boolean;
    error: string | null;
    reload: () => Promise<void>;
}

const MenuContext = createContext<MenuContextValue | null>(null);

/** Menú del usuario (dashboards por división y grupo). Vive en el shell, así sobrevive a los cambios de ruta. */
export function MenuProvider({ children }: { children: ReactNode }) {
    const { user } = useAuth();
    const [menu, setMenu] = useState<Menu | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const reload = useCallback(async () => {
        try {
            setMenu(await getMenu());
            setError(null);
        } catch (e) {
            setError(e instanceof ApiError ? e.message : 'No se pudo cargar el menú.');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        if (user) void reload();
    }, [user?.id, reload]); // eslint-disable-line react-hooks/exhaustive-deps

    return <MenuContext.Provider value={{ menu, loading, error, reload }}>{children}</MenuContext.Provider>;
}

export function useMenu(): MenuContextValue {
    const ctx = useContext(MenuContext);
    if (!ctx) throw new Error('useMenu debe usarse dentro de MenuProvider');
    return ctx;
}
