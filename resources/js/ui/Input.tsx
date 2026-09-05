import { forwardRef, type InputHTMLAttributes } from 'react';

interface Props extends InputHTMLAttributes<HTMLInputElement> {
    invalid?: boolean;
}

export const Input = forwardRef<HTMLInputElement, Props>(function Input({ invalid = false, className = '', ...rest }, ref) {
    return (
        <input
            ref={ref}
            {...rest}
            aria-invalid={invalid || undefined}
            className={`h-8 w-full rounded border bg-white px-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-500 disabled:bg-slate-100 ${
                invalid ? 'border-red-600' : 'border-slate-300'
            } ${className}`}
        />
    );
});
