import { useCallback, useEffect, useRef, useState, type DependencyList } from 'react';
import { ApiError } from '@/api/client';

interface State<T> {
    data: T | null;
    error: string | null;
    status: number | null;
    loading: boolean;
}

/**
 * Carga de datos mínima: ejecuta `request` al montar y cuando cambian `deps`.
 * Ignora respuestas de peticiones viejas si las dependencias cambiaron mientras tanto.
 */
export function useRequest<T>(request: () => Promise<T>, deps: DependencyList) {
    const [state, setState] = useState<State<T>>({ data: null, error: null, status: null, loading: true });
    const version = useRef(0);

    const run = useCallback(() => {
        const current = ++version.current;
        setState((s) => ({ ...s, loading: true, error: null }));
        request()
            .then((data) => {
                if (current === version.current) setState({ data, error: null, status: null, loading: false });
            })
            .catch((e: unknown) => {
                if (current !== version.current) return;
                const message = e instanceof ApiError ? e.message : 'No se pudo conectar con el servidor.';
                const status = e instanceof ApiError ? e.status : null;
                setState({ data: null, error: message, status, loading: false });
            });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, deps);

    useEffect(() => {
        run();
    }, [run]);

    return { ...state, reload: run, setData: (data: T) => setState((s) => ({ ...s, data })) };
}
