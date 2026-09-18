import { useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';

/**
 * Tooltip inmediato para el menú contraído. Se dibuja en un portal con posición fija, a la
 * derecha del elemento, así el scroll del nav no lo recorta y no depende del retraso del
 * tooltip nativo del navegador.
 */
export function RailTooltip({ label, enabled = true, children }: { label: string; enabled?: boolean; children: ReactNode }) {
    const [pos, setPos] = useState<{ top: number; left: number } | null>(null);

    if (!enabled) return <>{children}</>;

    function show(e: React.SyntheticEvent<HTMLElement>) {
        const r = e.currentTarget.getBoundingClientRect();
        setPos({ top: r.top + r.height / 2, left: r.right + 8 });
    }

    return (
        <div onMouseEnter={show} onMouseLeave={() => setPos(null)} onFocus={show} onBlur={() => setPos(null)}>
            {children}
            {pos &&
                createPortal(
                    <div
                        role="tooltip"
                        style={{ position: 'fixed', top: pos.top, left: pos.left, transform: 'translateY(-50%)' }}
                        className="pointer-events-none z-50 rounded bg-slate-900 px-2 py-1 text-xs font-medium whitespace-nowrap text-white shadow-lg"
                    >
                        {label}
                    </div>,
                    document.body,
                )}
        </div>
    );
}
