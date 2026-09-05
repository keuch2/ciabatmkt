import type { ReactNode } from 'react';

type Tone = 'error' | 'success' | 'info';

const tones: Record<Tone, string> = {
    error: 'border-red-300 bg-red-50 text-red-800',
    success: 'border-green-300 bg-green-50 text-green-800',
    info: 'border-slate-300 bg-slate-50 text-slate-700',
};

export function Alert({ tone = 'info', children }: { tone?: Tone; children: ReactNode }) {
    return (
        <div role={tone === 'error' ? 'alert' : 'status'} className={`rounded border px-3 py-2 text-sm ${tones[tone]}`}>
            {children}
        </div>
    );
}
