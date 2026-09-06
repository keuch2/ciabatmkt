import { Input } from '@/ui/Input';
import type { ControlProps } from './types';

export function DateControl({ id, definition, value, invalid, onChange }: ControlProps) {
    return (
        <Input
            id={id}
            type="date"
            value={String(value)}
            min={typeof definition.min === 'string' ? definition.min : undefined}
            max={typeof definition.max === 'string' ? definition.max : undefined}
            invalid={invalid}
            onChange={(e) => {
                if (e.target.value) onChange(e.target.value);
            }}
        />
    );
}
