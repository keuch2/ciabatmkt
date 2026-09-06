import type { ParamScalar } from '@/api/dashboards';

/** Representación compacta de un valor escalar para tablas e historial. */
export function formatScalar(value: ParamScalar | null | undefined, unit?: string | null): string {
    if (value === null || value === undefined) return '—';
    if (typeof value === 'boolean') return value ? 'Sí' : 'No';
    if (typeof value === 'number') return `${value.toLocaleString('es-PY')}${unit ? ` ${unit}` : ''}`;
    return value === '' ? '(vacío)' : value;
}

export function formatDateTime(iso: string | null | undefined): string {
    if (!iso) return '';
    return new Date(iso).toLocaleString('es-PY', { dateStyle: 'short', timeStyle: 'short' });
}
