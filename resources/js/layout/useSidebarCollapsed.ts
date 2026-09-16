import { useCallback, useState } from 'react';

const KEY = 'ciabay.sidebar.collapsed';

function read(): boolean {
    try {
        return localStorage.getItem(KEY) === '1';
    } catch {
        return false;
    }
}

/** Estado del menú lateral (expandido o rail de íconos), recordado por navegador. Por defecto expandido. */
export function useSidebarCollapsed(): [boolean, (next: boolean) => void] {
    const [collapsed, setCollapsed] = useState<boolean>(read);
    const set = useCallback((next: boolean) => {
        setCollapsed(next);
        try {
            localStorage.setItem(KEY, next ? '1' : '0');
        } catch {
            // Sin almacenamiento disponible: el estado dura lo que dure la página.
        }
    }, []);
    return [collapsed, set];
}
