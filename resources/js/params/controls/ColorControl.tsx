import { useEffect, useState } from 'react';
import { Input } from '@/ui/Input';
import type { ControlProps } from './types';

const HEX = /^#[0-9a-fA-F]{6}$/;

export function ColorControl({ id, value, invalid, onChange }: ControlProps) {
    const [text, setText] = useState(String(value));
    useEffect(() => setText(String(value)), [value]);

    function commit(raw: string) {
        setText(raw);
        if (HEX.test(raw)) onChange(raw.toLowerCase());
    }

    return (
        <div className="flex items-center gap-2">
            <input
                type="color"
                aria-label="Elegir color"
                value={HEX.test(text) ? text : '#000000'}
                onChange={(e) => commit(e.target.value)}
                className="h-8 w-10 cursor-pointer rounded border border-slate-300 bg-white p-0.5"
            />
            <Input id={id} type="text" value={text} maxLength={7} invalid={invalid || !HEX.test(text)} onChange={(e) => commit(e.target.value)} className="font-mono" />
        </div>
    );
}
