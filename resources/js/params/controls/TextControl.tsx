import { Input } from '@/ui/Input';
import type { ControlProps } from './types';

export function TextControl({ id, definition, value, invalid, onChange }: ControlProps) {
    const text = String(value);
    return (
        <div className="relative">
            <Input id={id} type="text" value={text} maxLength={definition.maxLength} invalid={invalid} onChange={(e) => onChange(e.target.value)} />
            {definition.maxLength && (
                <span className="pointer-events-none absolute top-1/2 right-2 -translate-y-1/2 text-[10px] tabular-nums text-slate-400">
                    {text.length}/{definition.maxLength}
                </span>
            )}
        </div>
    );
}
