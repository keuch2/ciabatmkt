import { useEffect, useMemo, useRef, useState } from 'react';
import { ApiError } from '@/api/client';
import type { ParamScalar } from '@/api/dashboards';
import { listRecords, putRecord, recordChanges, removeRecord, replaceRecords, seedRecords, type RecordEntry } from '@/api/records';
import { Spinner } from '@/ui/Spinner';
import { buildSrcdoc } from './buildSrcdoc';
import { parseFrameMessage, type HostToFrame } from './messages';
import type { Viewer } from './preamble';

interface Props {
    dashboardId: string;
    html: string;
    csp: string;
    viewer: Viewer | null;
    /** Valores efectivos de los parámetros escalares. Cada cambio se propaga con params:update. */
    params: Record<string, ParamScalar>;
    onParamChange?: (paramId: string, value: ParamScalar) => void;
    onError?: (message: string) => void;
}

const MIN_HEIGHT = 240;
const MAX_HEIGHT = 20000;
const READY_TIMEOUT_MS = 8000;
/** Cada cuánto se consultan cambios de otros usuarios en las colecciones abiertas. */
export const SYNC_INTERVAL_MS = 10000;

/**
 * Contenedor aislado del dashboard: iframe con sandbox sin allow-same-origin y srcdoc con el
 * preámbulo. Como el origen del iframe es opaco, la validación de mensajes se hace por
 * event.source (debe ser este iframe) y por forma del mensaje, nunca por event.origin.
 *
 * Además de los parámetros, atiende las peticiones de datos del dashboard (Dashboard.data)
 * contra la API con la sesión del usuario, y sincroniza cambios de otros usuarios por sondeo.
 */
export function DashboardFrame({ dashboardId, html, csp, viewer, params, onParamChange, onError }: Props) {
    const iframeRef = useRef<HTMLIFrameElement>(null);
    const [height, setHeight] = useState(480);
    const [status, setStatus] = useState<'loading' | 'ready' | 'timeout'>('loading');

    const latestParams = useRef(params);
    latestParams.current = params;
    const sentParams = useRef(params);
    const callbacks = useRef({ onParamChange, onError });
    callbacks.current = { onParamChange, onError };
    /** Colecciones que el dashboard abrió, con el cursor de la última sincronización. */
    const cursors = useRef<Map<string, string>>(new Map());

    // eslint-disable-next-line react-hooks/exhaustive-deps
    const srcdoc = useMemo(() => buildSrcdoc(html, latestParams.current, csp, viewer), [html, csp, viewer]);

    useEffect(() => {
        setStatus('loading');
        sentParams.current = latestParams.current;
        cursors.current = new Map();
    }, [srcdoc]);

    useEffect(() => {
        function send(message: HostToFrame) {
            iframeRef.current?.contentWindow?.postMessage(message, '*');
        }

        async function handleData(message: Extract<ReturnType<typeof parseFrameMessage>, { type: 'data:request' }>) {
            const { requestId, op, collection } = message;
            try {
                let result: unknown;
                switch (op) {
                    case 'list': {
                        const list = await listRecords(dashboardId, collection);
                        cursors.current.set(collection, list.server_time);
                        result = list;
                        break;
                    }
                    case 'put':
                        result = await putRecord(dashboardId, collection, message.recordId!, message.data, message.version);
                        break;
                    case 'remove':
                        result = await removeRecord(dashboardId, collection, message.recordId!);
                        break;
                    case 'seed': {
                        const seeded = await seedRecords(dashboardId, collection, message.records!);
                        cursors.current.set(collection, seeded.server_time);
                        result = seeded;
                        break;
                    }
                    case 'replace': {
                        const replaced = await replaceRecords(dashboardId, collection, message.records!);
                        cursors.current.set(collection, replaced.server_time);
                        result = replaced;
                        break;
                    }
                }
                send({ type: 'data:response', requestId, ok: true, result });
            } catch (e) {
                const error =
                    e instanceof ApiError
                        ? {
                              code: e.status === 409 ? 'conflict' : e.status === 403 ? 'forbidden' : e.status === 422 ? 'invalid' : 'error',
                              message: e.fieldError('data') ?? e.fieldError('collection') ?? e.fieldError('records') ?? e.fieldError('record_id') ?? e.message,
                              record: e.status === 409 ? (e.payload.record as RecordEntry) : undefined,
                          }
                        : { code: 'network', message: 'Sin conexión con el servidor.' };
                send({ type: 'data:response', requestId, ok: false, error });
            }
        }

        async function handleClipboard(requestId: string, text?: string, blob?: Blob) {
            try {
                if (text !== undefined) {
                    await navigator.clipboard.writeText(text);
                } else if (blob) {
                    await navigator.clipboard.write([new ClipboardItem({ [blob.type || 'image/png']: blob })]);
                }
                send({ type: 'clipboard:response', requestId, ok: true });
            } catch (e) {
                send({ type: 'clipboard:response', requestId, ok: false, message: e instanceof Error ? e.message : 'No se pudo copiar.' });
            }
        }

        function handle(event: MessageEvent) {
            const frame = iframeRef.current;
            if (!frame || event.source !== frame.contentWindow) return;

            const message = parseFrameMessage(event.data);
            if (!message) return;

            switch (message.type) {
                case 'dashboard:ready':
                    send({ type: 'params:init', params: sentParams.current });
                    setStatus('ready');
                    break;
                case 'dashboard:height':
                    setHeight(Math.min(MAX_HEIGHT, Math.max(MIN_HEIGHT, Math.ceil(message.height))));
                    break;
                case 'param:change':
                    callbacks.current.onParamChange?.(message.paramId, message.value);
                    break;
                case 'dashboard:error':
                    callbacks.current.onError?.(message.message);
                    break;
                case 'data:request':
                    void handleData(message);
                    break;
                case 'clipboard:write':
                    void handleClipboard(message.requestId, message.text, message.blob);
                    break;
            }
        }

        window.addEventListener('message', handle);
        return () => window.removeEventListener('message', handle);
    }, [dashboardId]);

    // Sincronización: cambios de otros usuarios en las colecciones abiertas, sólo con la pestaña visible.
    useEffect(() => {
        let busy = false;
        const timer = window.setInterval(async () => {
            if (busy || document.visibilityState !== 'visible' || cursors.current.size === 0) return;
            busy = true;
            try {
                for (const [collection, since] of Array.from(cursors.current.entries())) {
                    const changes = await recordChanges(dashboardId, collection, since);
                    cursors.current.set(collection, changes.server_time);
                    if (changes.changed.length || changes.deleted.length) {
                        iframeRef.current?.contentWindow?.postMessage(
                            { type: 'data:changes', collection, changed: changes.changed, deleted: changes.deleted } satisfies HostToFrame,
                            '*',
                        );
                    }
                }
            } catch {
                // Sin conexión: se reintenta en el próximo ciclo.
            } finally {
                busy = false;
            }
        }, SYNC_INTERVAL_MS);
        return () => window.clearInterval(timer);
    }, [dashboardId]);

    useEffect(() => {
        if (status !== 'loading') return;
        const timer = window.setTimeout(() => setStatus('timeout'), READY_TIMEOUT_MS);
        return () => window.clearTimeout(timer);
    }, [status, srcdoc]);

    // Propagar sólo las claves que cambiaron respecto de lo último enviado.
    useEffect(() => {
        const frame = iframeRef.current;
        if (!frame?.contentWindow || status === 'loading') return;

        const changed: Record<string, ParamScalar> = {};
        for (const [key, value] of Object.entries(params)) {
            if (sentParams.current[key] !== value) changed[key] = value;
        }
        if (Object.keys(changed).length === 0) return;

        sentParams.current = { ...sentParams.current, ...changed };
        frame.contentWindow.postMessage({ type: 'params:update', params: changed } satisfies HostToFrame, '*');
    }, [params, status]);

    return (
        <div className="relative w-full overflow-hidden rounded border border-slate-200 bg-white">
            {status === 'loading' && (
                <div className="absolute inset-0 z-10 flex items-center justify-center bg-white/80">
                    <Spinner className="h-5 w-5" />
                </div>
            )}
            {status === 'timeout' && (
                <div className="border-b border-amber-200 bg-amber-50 px-3 py-1.5 text-xs text-amber-800">
                    El dashboard no avisó que terminó de inicializar (no llamó a Dashboard.ready()). Se muestra igual.
                </div>
            )}
            <iframe
                ref={iframeRef}
                title="Dashboard"
                sandbox="allow-scripts allow-modals allow-downloads allow-popups"
                allow="clipboard-write; clipboard-read"
                srcDoc={srcdoc}
                style={{ height }}
                className="block w-full border-0"
            />
        </div>
    );
}
