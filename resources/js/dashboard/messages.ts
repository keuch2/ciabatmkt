import type { ParamScalar } from '@/api/dashboards';

/**
 * Protocolo postMessage entre el contenedor y el iframe. Son los únicos mensajes válidos;
 * cualquier otro se descarta. Ver kit/ESPECIFICACION.md.
 */

export type FrameToHost =
    | { type: 'dashboard:ready' }
    | { type: 'dashboard:height'; height: number }
    | { type: 'param:change'; paramId: string; value: ParamScalar }
    | { type: 'dashboard:error'; message: string };

export type HostToFrame =
    | { type: 'params:init'; params: Record<string, ParamScalar> }
    | { type: 'params:update'; params: Record<string, ParamScalar> };

export function isParamScalar(value: unknown): value is ParamScalar {
    return typeof value === 'string' || typeof value === 'boolean' || (typeof value === 'number' && Number.isFinite(value));
}

/** Valida la forma de un mensaje recibido del iframe. Devuelve null si no es reconocible. */
export function parseFrameMessage(data: unknown): FrameToHost | null {
    if (!data || typeof data !== 'object') return null;
    const m = data as Record<string, unknown>;

    switch (m.type) {
        case 'dashboard:ready':
            return { type: 'dashboard:ready' };
        case 'dashboard:height':
            return typeof m.height === 'number' && Number.isFinite(m.height) ? { type: 'dashboard:height', height: m.height } : null;
        case 'param:change':
            return typeof m.paramId === 'string' && isParamScalar(m.value)
                ? { type: 'param:change', paramId: m.paramId, value: m.value }
                : null;
        case 'dashboard:error':
            return typeof m.message === 'string' ? { type: 'dashboard:error', message: m.message.slice(0, 500) } : null;
        default:
            return null;
    }
}
