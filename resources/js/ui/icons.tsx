import type { ReactNode, SVGProps } from 'react';

/**
 * Set fijo de íconos inline (sin librerías). ICON_KEYS debe coincidir con
 * App\Support\DashboardIcons::KEYS en PHP; un test compara ambas listas.
 */
export const ICON_KEYS = [
    'chart-bar', 'chart-line', 'chart-pie', 'trending-up', 'table', 'grid',
    'dollar', 'cart', 'tag', 'users', 'user', 'building',
    'factory', 'truck', 'package', 'map-pin', 'calendar', 'clock',
    'target', 'flag', 'bell', 'clipboard', 'briefcase', 'globe',
] as const;

export type IconKey = (typeof ICON_KEYS)[number];

export const ICON_LABELS: Record<IconKey, string> = {
    'chart-bar': 'Gráfico de barras', 'chart-line': 'Gráfico de líneas', 'chart-pie': 'Gráfico de torta', 'trending-up': 'Tendencia',
    table: 'Tabla', grid: 'Cuadrícula', dollar: 'Dinero', cart: 'Ventas', tag: 'Etiqueta', users: 'Equipo', user: 'Persona',
    building: 'Edificio', factory: 'Planta', truck: 'Transporte', package: 'Paquete', 'map-pin': 'Ubicación', calendar: 'Calendario',
    clock: 'Reloj', target: 'Objetivo', flag: 'Bandera', bell: 'Alerta', clipboard: 'Planilla', briefcase: 'Negocios', globe: 'Global',
};

/** Íconos de la interfaz (menú, acciones). No se ofrecen a los dashboards. */
export type UiIconKey = 'home' | 'chevron-left' | 'chevron-right' | 'logout' | 'settings' | 'book' | 'history' | 'layers' | 'menu';

const PATHS: Record<IconKey | UiIconKey, ReactNode> = {
    'chart-bar': <><path d="M3 20h18" /><rect x="5" y="10" width="3" height="7" /><rect x="10.5" y="5" width="3" height="12" /><rect x="16" y="13" width="3" height="4" /></>,
    'chart-line': <><path d="M3 20h18" /><path d="M4 15l5-5 4 3 7-8" /></>,
    'chart-pie': <><path d="M12 3a9 9 0 1 0 9 9h-9z" /><path d="M14 3.2A9 9 0 0 1 20.8 10H14z" /></>,
    'trending-up': <><path d="M3 17l6-6 4 4 8-8" /><path d="M15 7h6v6" /></>,
    table: <><rect x="3" y="4" width="18" height="16" rx="1.5" /><path d="M3 10h18M3 15h18M9 4v16" /></>,
    grid: <><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" /></>,
    dollar: <><path d="M12 2v20" /><path d="M16.5 6.5a4 4 0 0 0-4-2.5h-1.5a3.5 3.5 0 0 0 0 7h2a3.5 3.5 0 0 1 0 7h-1.5a4 4 0 0 1-4-2.5" /></>,
    cart: <><path d="M3 4h2l2.4 11h11l2-7H7" /><circle cx="9" cy="19" r="1.3" /><circle cx="17" cy="19" r="1.3" /></>,
    tag: <><path d="M3 12V4h8l9 9-8 8z" /><circle cx="7.5" cy="8.5" r="1.2" /></>,
    users: <><circle cx="9" cy="8" r="3.5" /><path d="M2.5 20a6.5 6.5 0 0 1 13 0" /><path d="M16 4.5a3.5 3.5 0 0 1 0 7" /><path d="M17.5 13.5a6.5 6.5 0 0 1 4 6.5" /></>,
    user: <><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></>,
    building: <><rect x="4" y="3" width="16" height="18" rx="1" /><path d="M8 7h2M14 7h2M8 11h2M14 11h2M8 15h2M14 15h2M10 21v-3h4v3" /></>,
    factory: <><path d="M3 21V10l5 3V10l5 3V10l5 3V5h3v16z" /><path d="M7 17h2M12 17h2M17 17h2" /></>,
    truck: <><path d="M2 6h11v10H2z" /><path d="M13 10h5l3 3v3h-8z" /><circle cx="6" cy="18" r="1.8" /><circle cx="17" cy="18" r="1.8" /></>,
    package: <><path d="M12 3l9 4.5v9L12 21l-9-4.5v-9z" /><path d="M3 7.5l9 4.5 9-4.5M12 12v9" /></>,
    'map-pin': <><path d="M12 21s-6-5.5-6-11a6 6 0 0 1 12 0c0 5.5-6 11-6 11z" /><circle cx="12" cy="10" r="2" /></>,
    calendar: <><rect x="3" y="5" width="18" height="16" rx="1.5" /><path d="M3 10h18M8 3v4M16 3v4" /></>,
    clock: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
    target: <><circle cx="12" cy="12" r="9" /><circle cx="12" cy="12" r="5" /><circle cx="12" cy="12" r="1.2" /></>,
    flag: <><path d="M5 21V4" /><path d="M5 4h12l-2 4 2 4H5" /></>,
    bell: <><path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z" /><path d="M10 20a2 2 0 0 0 4 0" /></>,
    clipboard: <><rect x="5" y="4" width="14" height="17" rx="1.5" /><path d="M9 4V3h6v1M8 10h8M8 14h8M8 18h5" /></>,
    briefcase: <><rect x="3" y="7" width="18" height="13" rx="1.5" /><path d="M9 7V4h6v3M3 13h18" /></>,
    globe: <><circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" /></>,
    home: <><path d="M3 11l9-7 9 7" /><path d="M5 10v10h5v-6h4v6h5V10" /></>,
    'chevron-left': <path d="M15 5l-7 7 7 7" />,
    'chevron-right': <path d="M9 5l7 7-7 7" />,
    logout: <><path d="M10 4H5v16h5" /><path d="M14 8l4 4-4 4M18 12H9" /></>,
    settings: <><circle cx="12" cy="12" r="3" /><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1" /></>,
    book: <><path d="M4 4h12a3 3 0 0 1 3 3v13H7a3 3 0 0 0-3 3z" /><path d="M4 4v16" /></>,
    history: <><path d="M3 12a9 9 0 1 0 3-6.7" /><path d="M3 4v5h5" /><path d="M12 7v5l3 2" /></>,
    layers: <><path d="M12 3l9 5-9 5-9-5z" /><path d="M3 13l9 5 9-5M3 17l9 5 9-5" /></>,
    menu: <path d="M4 7h16M4 12h16M4 17h16" />,
};

export function Icon({ name, className = 'h-4 w-4', ...rest }: { name: IconKey | UiIconKey } & Omit<SVGProps<SVGSVGElement>, 'name'>) {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" className={className} {...rest}>
            {PATHS[name]}
        </svg>
    );
}

export function isIconKey(value: unknown): value is IconKey {
    return typeof value === 'string' && (ICON_KEYS as readonly string[]).includes(value);
}

/** Iniciales del título: primeras letras de las dos primeras palabras. */
export function initials(title: string): string {
    const words = title.trim().split(/\s+/).filter(Boolean);
    return words
        .slice(0, 2)
        .map((w) => w[0]?.toUpperCase() ?? '')
        .join('') || '?';
}

/** Ícono de un dashboard: el elegido por el admin o un cuadro con las iniciales del título. */
export function DashboardIcon({ icon, title, className = 'h-6 w-6' }: { icon: string | null | undefined; title: string; className?: string }) {
    if (isIconKey(icon)) {
        return (
            <span className={`inline-flex shrink-0 items-center justify-center rounded bg-slate-200/70 text-slate-700 ${className}`}>
                <Icon name={icon} className="h-[62%] w-[62%]" />
            </span>
        );
    }
    return (
        <span className={`inline-flex shrink-0 items-center justify-center rounded bg-slate-200/70 text-[10px] font-semibold uppercase text-slate-700 ${className}`}>
            {initials(title)}
        </span>
    );
}
