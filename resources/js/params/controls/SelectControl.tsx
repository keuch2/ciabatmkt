import type { ControlProps } from './types';

/** select: los values pueden ser string o número; se mapea por índice para no perder el tipo. */
export function SelectControl({ id, definition, value, invalid, onChange }: ControlProps) {
    const options = definition.options ?? [];
    const index = options.findIndex((o) => o.value === value);

    return (
        <select
            id={id}
            value={index}
            aria-invalid={invalid || undefined}
            onChange={(e) => {
                const option = options[Number(e.target.value)];
                if (option) onChange(option.value);
            }}
            className={`h-8 w-full rounded border bg-white px-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-500 ${
                invalid ? 'border-red-600' : 'border-slate-300'
            }`}
        >
            {index === -1 && <option value={-1}>—</option>}
            {options.map((o, i) => (
                <option key={String(o.value)} value={i}>
                    {o.label}
                </option>
            ))}
        </select>
    );
}
