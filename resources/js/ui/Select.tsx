import type { SelectHTMLAttributes } from 'react';

export function Select({ className = '', invalid = false, ...rest }: SelectHTMLAttributes<HTMLSelectElement> & { invalid?: boolean }) {
    return (
        <select
            {...rest}
            aria-invalid={invalid || undefined}
            className={`h-8 w-full rounded border bg-white px-2 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-500 ${
                invalid ? 'border-red-600' : 'border-slate-300'
            } ${className}`}
        />
    );
}
