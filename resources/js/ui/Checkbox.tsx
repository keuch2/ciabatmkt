import type { InputHTMLAttributes, ReactNode } from 'react';

export function Checkbox({ label, hint, className = '', ...rest }: InputHTMLAttributes<HTMLInputElement> & { label: ReactNode; hint?: ReactNode }) {
    return (
        <label className={`flex items-start gap-2 text-sm ${rest.disabled ? 'text-slate-400' : 'text-slate-700'} ${className}`}>
            <input type="checkbox" {...rest} className="mt-0.5 h-4 w-4 rounded border-slate-300 accent-slate-800" />
            <span>
                {label}
                {hint && <span className="block text-xs text-slate-500">{hint}</span>}
            </span>
        </label>
    );
}
