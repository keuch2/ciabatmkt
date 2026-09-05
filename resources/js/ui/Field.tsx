import type { ReactNode } from 'react';

interface Props {
    label: string;
    htmlFor: string;
    error?: string;
    hint?: string;
    children: ReactNode;
}

export function Field({ label, htmlFor, error, hint, children }: Props) {
    return (
        <div className="space-y-1">
            <label htmlFor={htmlFor} className="block text-xs font-medium text-slate-700">
                {label}
            </label>
            {children}
            {error ? (
                <p className="text-xs text-red-700" role="alert">
                    {error}
                </p>
            ) : hint ? (
                <p className="text-xs text-slate-500">{hint}</p>
            ) : null}
        </div>
    );
}
