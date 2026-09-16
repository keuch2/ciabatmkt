import { useEffect, useRef, type ReactNode } from 'react';

export interface ActionItem {
    label: ReactNode;
    onClick: () => void;
    tone?: 'default' | 'danger';
    disabled?: boolean;
}

/** Menú desplegable compacto para acciones secundarias de una fila. Se cierra al elegir o al hacer clic afuera. */
export function ActionsMenu({ label = 'Más', items }: { label?: string; items: ActionItem[] }) {
    const ref = useRef<HTMLDetailsElement>(null);

    useEffect(() => {
        function close(e: MouseEvent) {
            if (ref.current?.open && !ref.current.contains(e.target as Node)) ref.current.open = false;
        }
        document.addEventListener('click', close);
        return () => document.removeEventListener('click', close);
    }, []);

    return (
        <details ref={ref} className="relative inline-block">
            <summary className="flex h-8 cursor-pointer list-none items-center gap-1 rounded px-2 text-sm text-slate-700 hover:bg-slate-100 [&::-webkit-details-marker]:hidden">
                {label} <span aria-hidden="true">▾</span>
            </summary>
            <div className="absolute right-0 z-20 mt-1 min-w-40 rounded border border-slate-200 bg-white py-1 shadow-lg">
                {items.map((item, i) => (
                    <button
                        key={i}
                        type="button"
                        disabled={item.disabled}
                        onClick={() => {
                            if (ref.current) ref.current.open = false;
                            item.onClick();
                        }}
                        className={`block w-full px-3 py-1.5 text-left text-sm hover:bg-slate-100 disabled:opacity-40 ${item.tone === 'danger' ? 'text-red-700' : 'text-slate-800'}`}
                    >
                        {item.label}
                    </button>
                ))}
            </div>
        </details>
    );
}
