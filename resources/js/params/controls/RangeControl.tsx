import type { ControlProps } from './types';

export function RangeControl({ id, definition, value, onChange }: ControlProps) {
    const min = typeof definition.min === 'number' ? definition.min : 0;
    const max = typeof definition.max === 'number' ? definition.max : 100;
    const n = typeof value === 'number' ? value : Number(value);

    return (
        <div className="flex items-center gap-3">
            <input
                id={id}
                type="range"
                min={min}
                max={max}
                step={definition.step ?? 1}
                value={n}
                onChange={(e) => onChange(Number(e.target.value))}
                className="h-1.5 w-full cursor-pointer accent-slate-800"
                aria-valuemin={min}
                aria-valuemax={max}
                aria-valuenow={n}
            />
            <span className="w-16 shrink-0 text-right font-mono text-xs tabular-nums text-slate-700">
                {n.toLocaleString('es-PY')}
                {definition.unit ? ` ${definition.unit}` : ''}
            </span>
        </div>
    );
}
