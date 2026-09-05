import type { ReactNode } from 'react';

export function AuthLayout({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="flex min-h-screen items-center justify-center px-4">
            <div className="w-full max-w-sm">
                <div className="mb-4">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Ciabay Dashboards</p>
                    <h1 className="text-lg font-semibold text-slate-900">{title}</h1>
                </div>
                <div className="rounded border border-slate-200 bg-white p-5 shadow-sm">{children}</div>
            </div>
        </div>
    );
}
