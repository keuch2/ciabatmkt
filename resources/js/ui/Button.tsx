import type { ButtonHTMLAttributes } from 'react';
import { Spinner } from './Spinner';

type Variant = 'primary' | 'secondary' | 'danger' | 'ghost';

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    loading?: boolean;
}

const styles: Record<Variant, string> = {
    primary: 'bg-slate-800 text-white hover:bg-slate-700 disabled:bg-slate-400',
    secondary: 'border border-slate-300 bg-white text-slate-800 hover:bg-slate-50 disabled:text-slate-400',
    danger: 'bg-red-700 text-white hover:bg-red-600 disabled:bg-red-300',
    ghost: 'text-slate-700 hover:bg-slate-200 disabled:text-slate-400',
};

export function Button({ variant = 'primary', loading = false, className = '', children, disabled, ...rest }: Props) {
    return (
        <button
            {...rest}
            disabled={disabled || loading}
            className={`inline-flex h-8 items-center justify-center gap-2 rounded px-3 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-500 disabled:cursor-not-allowed ${styles[variant]} ${className}`}
        >
            {loading && <Spinner className="h-3.5 w-3.5 border-white/40 border-t-white" />}
            {children}
        </button>
    );
}
