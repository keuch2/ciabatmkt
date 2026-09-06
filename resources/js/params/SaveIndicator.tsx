import type { SaveStatus } from './useParamState';

const LABELS: Record<SaveStatus, { text: string; className: string }> = {
    idle: { text: '', className: '' },
    pending: { text: 'Cambios sin guardar…', className: 'text-slate-500' },
    saving: { text: 'Guardando…', className: 'text-slate-500' },
    saved: { text: 'Guardado', className: 'text-green-700' },
    error: { text: 'Error al guardar', className: 'text-red-700' },
};

export function SaveIndicator({ status }: { status: SaveStatus }) {
    const label = LABELS[status];
    return (
        <span role="status" aria-live="polite" className={`text-xs ${label.className}`}>
            {label.text}
        </span>
    );
}
