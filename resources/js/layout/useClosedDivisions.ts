import { useCallback, useState } from 'react';

const KEY = 'ciabay.sidebar.closedDivisions';

function read(): string[] {
    try {
        const parsed = JSON.parse(localStorage.getItem(KEY) ?? '[]');
        return Array.isArray(parsed) ? parsed.filter((x) => typeof x === 'string') : [];
    } catch {
        return [];
    }
}

/** Divisiones plegadas del menú, recordadas por navegador. Por defecto todas abiertas. */
export function useClosedDivisions(): [Set<string>, (id: string) => void] {
    const [closed, setClosed] = useState<Set<string>>(() => new Set(read()));
    const toggle = useCallback((id: string) => {
        setClosed((current) => {
            const next = new Set(current);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            try {
                localStorage.setItem(KEY, JSON.stringify([...next]));
            } catch {
                // Sin almacenamiento: dura lo que dure la página.
            }
            return next;
        });
    }, []);
    return [closed, toggle];
}
