import { useEffect, useState } from 'react';
import { Input } from '@/ui/Input';
import type { ControlProps } from './types';

/** number: el texto se valida localmente; sólo se propaga un número válido dentro del rango. */
export function NumberControl({ id, definition, value, invalid, onChange }: ControlProps) {
    const [text, setText] = useState(String(value));
    const [localError, setLocalError] = useState<string | null>(null);

    useEffect(() => {
        setText(String(value));
        setLocalError(null);
    }, [value]);

    const min = typeof definition.min === 'number' ? definition.min : undefined;
    const max = typeof definition.max === 'number' ? definition.max : undefined;

    function commit(raw: string) {
        setText(raw);
        if (raw.trim() === '') {
            setLocalError('Ingresá un número.');
            return;
        }
        const n = Number(raw);
        if (!Number.isFinite(n)) {
            setLocalError('Ingresá un número válido.');
            return;
        }
        if (min !== undefined && n < min) {
            setLocalError(`Mínimo ${min.toLocaleString('es-PY')}.`);
            return;
        }
        if (max !== undefined && n > max) {
            setLocalError(`Máximo ${max.toLocaleString('es-PY')}.`);
            return;
        }
        setLocalError(null);
        onChange(n);
    }

    return (
        <div className="flex items-center gap-2">
            <Input
                id={id}
                type="number"
                inputMode="decimal"
                min={min}
                max={max}
                step={definition.step ?? 'any'}
                value={text}
                invalid={invalid || localError !== null}
                onChange={(e) => commit(e.target.value)}
                className="font-mono"
            />
            {definition.unit && <span className="shrink-0 text-xs text-slate-500">{definition.unit}</span>}
            {localError && (
                <span className="sr-only" role="alert">
                    {localError}
                </span>
            )}
            {localError && <span className="absolute right-3 -bottom-0.5 text-[11px] text-red-700">{localError}</span>}
        </div>
    );
}
