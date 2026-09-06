import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ApiError } from '@/api/client';
import type { ParamScalar, ResolvedParam } from '@/api/dashboards';
import { resetAllParams, resetParam, saveParam, type ParamScope } from '@/api/params';

export type SaveStatus = 'idle' | 'pending' | 'saving' | 'saved' | 'error';

export interface ParamEntry extends ResolvedParam {
    status: SaveStatus;
    error: string | null;
}

export const DEBOUNCE_MS = 400;
const SAVED_FLASH_MS = 1500;

/**
 * Estado de los parámetros de un dashboard con guardado optimista.
 *
 * - Cada cambio se refleja al instante en `values` (lo que ve el iframe).
 * - La escritura se envía con debounce de 400 ms por parámetro; arrastrar un range no
 *   dispara cincuenta peticiones.
 * - Las peticiones de un mismo parámetro se serializan: si llega un valor nuevo mientras
 *   una escritura está en vuelo, se envía el último al terminar.
 * - Un reset cancela el debounce pendiente y descarta la respuesta de cualquier escritura
 *   en vuelo de ese parámetro.
 */
export function useParamState(dashboardId: string, initial: Record<string, ResolvedParam>, scope: ParamScope) {
    const [entries, setEntries] = useState<Record<string, ParamEntry>>(() => fromResolved(initial));

    const timers = useRef<Record<string, number>>({});
    const inflight = useRef<Record<string, boolean>>({});
    const dirty = useRef<Record<string, ParamScalar | undefined>>({});
    const generation = useRef<Record<string, number>>({});
    const latest = useRef(entries);
    latest.current = entries;

    useEffect(() => {
        setEntries(fromResolved(initial));
        Object.values(timers.current).forEach((t) => window.clearTimeout(t));
        timers.current = {};
        dirty.current = {};
    }, [initial, dashboardId, scope]);

    useEffect(() => () => Object.values(timers.current).forEach((t) => window.clearTimeout(t)), []);

    const patch = useCallback((id: string, changes: Partial<ParamEntry>) => {
        setEntries((all) => (all[id] ? { ...all, [id]: { ...all[id], ...changes } } : all));
    }, []);

    const flush = useCallback(
        async (id: string, value: ParamScalar) => {
            if (inflight.current[id]) {
                dirty.current[id] = value;
                return;
            }
            inflight.current[id] = true;
            const gen = generation.current[id] ?? 0;
            patch(id, { status: 'saving', error: null });

            try {
                const saved = await saveParam(dashboardId, id, value, scope);
                if ((generation.current[id] ?? 0) === gen) {
                    patch(id, { ...saved, status: 'saved', error: null });
                    window.setTimeout(() => {
                        setEntries((all) => (all[id]?.status === 'saved' ? { ...all, [id]: { ...all[id], status: 'idle' } } : all));
                    }, SAVED_FLASH_MS);
                }
            } catch (e) {
                if ((generation.current[id] ?? 0) === gen) {
                    const message = e instanceof ApiError ? (e.fieldError('value') ?? e.fieldError('param_id') ?? e.message) : 'No se pudo guardar: sin conexión con el servidor.';
                    patch(id, { status: 'error', error: message });
                }
            } finally {
                inflight.current[id] = false;
                const next = dirty.current[id];
                if (next !== undefined) {
                    dirty.current[id] = undefined;
                    void flush(id, next);
                }
            }
        },
        [dashboardId, scope, patch],
    );

    /** Cambio desde un control o desde el propio dashboard: refleja ya y programa el guardado. */
    const setValue = useCallback(
        (id: string, value: ParamScalar) => {
            if (!latest.current[id]) return;
            patch(id, { value, status: 'pending', error: null });
            window.clearTimeout(timers.current[id]);
            timers.current[id] = window.setTimeout(() => {
                delete timers.current[id];
                void flush(id, value);
            }, DEBOUNCE_MS);
        },
        [flush, patch],
    );

    const reset = useCallback(
        async (id: string) => {
            window.clearTimeout(timers.current[id]);
            delete timers.current[id];
            dirty.current[id] = undefined;
            generation.current[id] = (generation.current[id] ?? 0) + 1;
            patch(id, { status: 'saving', error: null });
            try {
                const resolved = await resetParam(dashboardId, id, scope);
                patch(id, { ...resolved, status: 'idle', error: null });
            } catch (e) {
                patch(id, { status: 'error', error: e instanceof ApiError ? e.message : 'No se pudo restablecer: sin conexión con el servidor.' });
            }
        },
        [dashboardId, scope, patch],
    );

    const resetAll = useCallback(async () => {
        Object.values(timers.current).forEach((t) => window.clearTimeout(t));
        timers.current = {};
        dirty.current = {};
        for (const id of Object.keys(latest.current)) generation.current[id] = (generation.current[id] ?? 0) + 1;
        setEntries((all) => Object.fromEntries(Object.entries(all).map(([k, v]) => [k, { ...v, status: 'saving' as const, error: null }])));
        try {
            const result = await resetAllParams(dashboardId, scope);
            setEntries(fromResolved(result.data));
        } catch (e) {
            const message = e instanceof ApiError ? e.message : 'No se pudo restablecer: sin conexión con el servidor.';
            setEntries((all) => Object.fromEntries(Object.entries(all).map(([k, v]) => [k, { ...v, status: 'error' as const, error: message }])));
        }
    }, [dashboardId, scope]);

    const values = useMemo(() => Object.fromEntries(Object.entries(entries).map(([k, v]) => [k, v.value])), [entries]);

    const overall = useMemo<SaveStatus>(() => {
        const statuses = Object.values(entries).map((e) => e.status);
        if (statuses.includes('error')) return 'error';
        if (statuses.includes('saving')) return 'saving';
        if (statuses.includes('pending')) return 'pending';
        if (statuses.includes('saved')) return 'saved';
        return 'idle';
    }, [entries]);

    const overrideCount = useMemo(() => Object.values(entries).filter((e) => e.has_override).length, [entries]);

    return { entries, values, overall, overrideCount, setValue, reset, resetAll };
}

function fromResolved(resolved: Record<string, ResolvedParam>): Record<string, ParamEntry> {
    return Object.fromEntries(Object.entries(resolved).map(([k, v]) => [k, { ...v, status: 'idle' as const, error: null }]));
}
