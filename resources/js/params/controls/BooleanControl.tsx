import type { ControlProps } from './types';

export function BooleanControl({ id, value, onChange }: ControlProps) {
    const on = Boolean(value);
    return (
        <button
            id={id}
            type="button"
            role="switch"
            aria-checked={on}
            onClick={() => onChange(!on)}
            className={`inline-flex h-6 w-11 shrink-0 items-center rounded-full border transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-500 ${
                on ? 'border-slate-800 bg-slate-800' : 'border-slate-300 bg-slate-200'
            }`}
        >
            <span className={`h-4 w-4 rounded-full bg-white shadow transition-transform ${on ? 'translate-x-6' : 'translate-x-1'}`} />
            <span className="sr-only">{on ? 'Sí' : 'No'}</span>
        </button>
    );
}
