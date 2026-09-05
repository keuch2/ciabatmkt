import { useEffect, useMemo, useRef, useState } from 'react';
import type { ParamScalar } from '@/api/dashboards';
import { Spinner } from '@/ui/Spinner';
import { buildSrcdoc } from './buildSrcdoc';
import { parseFrameMessage, type HostToFrame } from './messages';

interface Props {
    html: string;
    csp: string;
    /** Valores efectivos actuales. Cada cambio se propaga al iframe con params:update. */
    params: Record<string, ParamScalar>;
    onParamChange?: (paramId: string, value: ParamScalar) => void;
    onError?: (message: string) => void;
}

const MIN_HEIGHT = 240;
const MAX_HEIGHT = 20000;
const READY_TIMEOUT_MS = 8000;

/**
 * Contenedor aislado del dashboard: iframe con sandbox="allow-scripts" (sin allow-same-origin)
 * y srcdoc con el preámbulo. Como el origen del iframe es opaco, la validación de mensajes se
 * hace por event.source (debe ser este iframe) y por forma del mensaje, nunca por event.origin.
 */
export function DashboardFrame({ html, csp, params, onParamChange, onError }: Props) {
    const iframeRef = useRef<HTMLIFrameElement>(null);
    const [height, setHeight] = useState(480);
    const [status, setStatus] = useState<'loading' | 'ready' | 'timeout'>('loading');

    const latestParams = useRef(params);
    latestParams.current = params;
    const sentParams = useRef(params);
    const callbacks = useRef({ onParamChange, onError });
    callbacks.current = { onParamChange, onError };

    // El documento sólo se reconstruye si cambia el HTML o la CSP; los params iniciales
    // son los vigentes en ese momento y luego viajan por mensajes.
    // eslint-disable-next-line react-hooks/exhaustive-deps
    const srcdoc = useMemo(() => buildSrcdoc(html, latestParams.current, csp), [html, csp]);

    useEffect(() => {
        setStatus('loading');
        sentParams.current = latestParams.current;
    }, [srcdoc]);

    useEffect(() => {
        function send(message: HostToFrame) {
            iframeRef.current?.contentWindow?.postMessage(message, '*');
        }

        function handle(event: MessageEvent) {
            const frame = iframeRef.current;
            if (!frame || event.source !== frame.contentWindow) return;

            const message = parseFrameMessage(event.data);
            if (!message) return;

            switch (message.type) {
                case 'dashboard:ready':
                    // params:init repite lo que ya viajó embebido en el preámbulo (se aplica en silencio).
                    // Si los valores cambiaron desde que se armó el srcdoc, el efecto de abajo manda
                    // la diferencia con params:update, que sí dispara onChange en el dashboard.
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
            }
        }

        window.addEventListener('message', handle);
        return () => window.removeEventListener('message', handle);
    }, []);

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
        <div className="relative self-start overflow-hidden rounded border border-slate-200 bg-white">
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
                sandbox="allow-scripts"
                srcDoc={srcdoc}
                style={{ height }}
                className="block w-full border-0"
            />
        </div>
    );
}
