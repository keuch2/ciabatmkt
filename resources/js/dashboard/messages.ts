import type { ParamScalar } from '@/api/dashboards';
import type { RecordEntry } from '@/api/records';

/**
 * Protocolo postMessage entre el contenedor y el iframe. Son los únicos mensajes válidos;
 * cualquier otro se descarta. Ver kit/ESPECIFICACION.md.
 */

export const DATA_OPS = ['list', 'put', 'remove', 'seed', 'replace'] as const;
export type DataOp = (typeof DATA_OPS)[number];

export type FrameToHost =
    | { type: 'dashboard:ready' }
    | { type: 'dashboard:height'; height: number }
    | { type: 'param:change'; paramId: string; value: ParamScalar }
    | { type: 'dashboard:error'; message: string }
    | {
          type: 'data:request';
          requestId: string;
          op: DataOp;
          collection: string;
          recordId?: string;
          data?: unknown;
          version?: number;
          records?: { id: string; data: unknown }[];
      }
    | { type: 'clipboard:write'; requestId: string; text?: string; blob?: Blob };

export type HostToFrame =
    | { type: 'params:init'; params: Record<string, ParamScalar> }
    | { type: 'params:update'; params: Record<string, ParamScalar> }
    | { type: 'data:response'; requestId: string; ok: true; result: unknown }
    | { type: 'data:response'; requestId: string; ok: false; error: { code: string; message: string; record?: RecordEntry } }
    | { type: 'data:changes'; collection: string; changed: RecordEntry[]; deleted: string[] }
    | { type: 'clipboard:response'; requestId: string; ok: boolean; message?: string };

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
        case 'data:request': {
            if (typeof m.requestId !== 'string' || typeof m.collection !== 'string') return null;
            if (!(DATA_OPS as readonly string[]).includes(String(m.op))) return null;
            const op = m.op as DataOp;
            if ((op === 'put' || op === 'remove') && typeof m.recordId !== 'string') return null;
            if ((op === 'seed' || op === 'replace') && !Array.isArray(m.records)) return null;
            return {
                type: 'data:request',
                requestId: m.requestId,
                op,
                collection: m.collection,
                recordId: typeof m.recordId === 'string' ? m.recordId : undefined,
                data: m.data,
                version: typeof m.version === 'number' ? m.version : undefined,
                records: Array.isArray(m.records) ? (m.records as { id: string; data: unknown }[]) : undefined,
            };
        }
        case 'clipboard:write':
            if (typeof m.requestId !== 'string') return null;
            if (typeof m.text !== 'string' && !(m.blob instanceof Blob)) return null;
            return { type: 'clipboard:write', requestId: m.requestId, text: typeof m.text === 'string' ? m.text : undefined, blob: m.blob instanceof Blob ? m.blob : undefined };
        default:
            return null;
    }
}
