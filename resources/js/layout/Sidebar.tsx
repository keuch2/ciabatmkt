import { NavLink, useLocation, useNavigate } from 'react-router-dom';
import type { MenuDashboard } from '@/api/menu';
import { useAuth } from '@/auth/AuthProvider';
import { useMenu } from '@/menu/MenuProvider';
import { DashboardIcon, Icon, initials, type UiIconKey } from '@/ui/icons';
import { RailTooltip } from '@/ui/RailTooltip';
import { Spinner } from '@/ui/Spinner';
import logo from '@/assets/logociabay.png';
import { useClosedDivisions } from './useClosedDivisions';

interface Props {
    collapsed: boolean;
    onToggle: () => void;
}

const ADMIN_LINKS: { to: string; label: string; icon: UiIconKey }[] = [
    { to: '/admin/dashboards', label: 'Dashboards', icon: 'grid' as UiIconKey },
    { to: '/admin/divisions', label: 'Divisiones', icon: 'layers' },
    { to: '/admin/users', label: 'Usuarios', icon: 'users' as UiIconKey },
    { to: '/admin/history', label: 'Historial', icon: 'history' },
    { to: '/admin/docs', label: 'Docs', icon: 'book' },
];

/**
 * Menú lateral dinámico: dashboards del usuario por división y grupo. En modo contraído es un
 * rail de íconos con tooltips nativos (title); un tooltip CSS se recortaría por el scroll del nav.
 */
export function Sidebar({ collapsed, onToggle }: Props) {
    const { user, logout } = useAuth();
    const { menu, loading, error } = useMenu();
    const navigate = useNavigate();
    const location = useLocation();
    const [closedDivisions, toggleDivision] = useClosedDivisions();

    async function handleLogout() {
        await logout();
        navigate('/login', { replace: true });
    }

    const linkClass = ({ isActive }: { isActive: boolean }) =>
        `flex items-center gap-2.5 rounded px-2 py-1.5 text-sm ${collapsed ? 'justify-center' : ''} ${
            isActive ? 'bg-slate-800 text-white' : 'text-slate-700 hover:bg-slate-200'
        }`;

    const sectionClass = `mt-3 mb-1 px-2 text-[10px] font-semibold uppercase tracking-wide text-slate-500 ${collapsed ? 'hidden' : ''}`;
    // Divisiones: etiqueta con fondo verde institucional.
    const divisionClass = 'inline-flex max-w-full items-center gap-1 rounded-sm bg-[#1a9e3f] py-0.5 pr-1.5 pl-2 text-[11px] font-bold uppercase tracking-wide text-white';
    // La división es un botón que pliega y despliega sus dashboards. Contraído el menú, es un cuadro
    // verde con ícono y el nombre en el tooltip.
    const divisionHeader = (id: string, name: string, open: boolean, count: number) => {
        const label = `${name} · ${open ? 'ocultar' : `mostrar ${count}`}`;
        return collapsed ? (
            <RailTooltip label={open ? name : `${name} (${count} ocultos)`}>
                <button type="button" onClick={() => toggleDivision(id)} aria-expanded={open} aria-label={label} className="mt-3 mb-1 flex w-full justify-center">
                    <span className={`flex h-6 w-6 items-center justify-center rounded-sm bg-[#1a9e3f] text-white ${open ? '' : 'opacity-60'}`}>
                        <Icon name="layers" className="h-3.5 w-3.5" />
                    </span>
                </button>
            </RailTooltip>
        ) : (
            <button type="button" onClick={() => toggleDivision(id)} aria-expanded={open} title={label} className="mt-3 mb-1 flex w-full items-center justify-between gap-2 rounded text-left hover:bg-slate-200/60">
                <span className={divisionClass}>
                    <span className="truncate">{name}</span>
                </span>
                <span className="flex shrink-0 items-center gap-1 pr-1 text-slate-500">
                    {!open && <span className="text-[10px] tabular-nums">{count}</span>}
                    <Icon name={open ? 'chevron-down' : 'chevron-right'} className="h-3.5 w-3.5" />
                </span>
            </button>
        );
    };

    const divider = collapsed ? <div className="my-2 border-t border-slate-200" aria-hidden="true" /> : null;

    const dashboardLink = (d: MenuDashboard, context: string) => (
        <RailTooltip key={d.id} label={`${d.title} · ${context}`} enabled={collapsed}>
            <NavLink to={`/dashboards/${d.id}`} className={linkClass}>
                <DashboardIcon icon={d.icon} title={d.title} className="h-6 w-6" />
                {!collapsed && (
                    <span className="min-w-0 flex-1 truncate">
                        {d.title}
                        {!d.is_published && <span className="ml-1 rounded bg-amber-100 px-1 text-[10px] text-amber-800">borrador</span>}
                    </span>
                )}
            </NavLink>
        </RailTooltip>
    );

    const hasAny = !!menu && (menu.divisions.length > 0 || menu.company.length > 0 || menu.unassigned.length > 0);

    return (
        <aside className={`sticky top-0 flex h-screen flex-col self-start border-r border-slate-200 bg-slate-50 ${collapsed ? 'w-14' : 'w-60'}`}>
            <div className={`border-b border-slate-200 ${collapsed ? 'px-2 py-3' : 'px-4 py-3'}`}>
                {collapsed ? (
                    <div className="flex h-8 items-center justify-center rounded bg-slate-800 text-xs font-bold text-white" title={`Ciabay · ${user?.name ?? ''}`}>
                        {initials(user?.name ?? 'C D')}
                    </div>
                ) : (
                    <img src={logo} alt="Ciabay" className="h-9 w-auto" title={user?.name} />
                )}
            </div>

            <nav className={`min-h-0 flex-1 overflow-y-auto overflow-x-hidden ${collapsed ? 'px-1.5 py-2' : 'px-2 py-2'}`} aria-label="Menú principal">
                <RailTooltip label="Inicio" enabled={collapsed}>
                    <NavLink to="/" end className={linkClass}>
                        <span className="inline-flex h-6 w-6 shrink-0 items-center justify-center">
                            <Icon name="home" className="h-4 w-4" />
                        </span>
                        {!collapsed && <span>Inicio</span>}
                    </NavLink>
                </RailTooltip>

                {loading && (
                    <div className="px-2 py-3">
                        <Spinner className="h-4 w-4" />
                    </div>
                )}
                {error && !collapsed && <p className="px-2 py-2 text-xs text-red-700">{error}</p>}

                {menu?.divisions.map((division) => {
                    const all = [...division.dashboards, ...division.groups.flatMap((g) => g.dashboards)];
                    // Nunca se oculta el dashboard que se está viendo: su división se muestra abierta.
                    const hasActive = all.some((d) => location.pathname.startsWith(`/dashboards/${d.id}`));
                    const open = !closedDivisions.has(division.id) || hasActive;
                    return (
                        <div key={division.id}>
                            {divisionHeader(division.id, division.name, open, all.length)}
                            {open && division.dashboards.map((d) => dashboardLink(d, division.name))}
                            {open &&
                                division.groups.map((group) => (
                                    <div key={group.id} className={collapsed ? '' : 'ml-2 border-l border-slate-200 pl-1.5'}>
                                        {!collapsed && <p className="mt-1.5 mb-0.5 px-2 text-[11px] font-medium text-slate-500">{group.name}</p>}
                                        {group.dashboards.map((d) => dashboardLink(d, `${division.name} / ${group.name}`))}
                                    </div>
                                ))}
                        </div>
                    );
                })}

                {menu && menu.company.length > 0 && (
                    <div>
                        <p className={sectionClass}>Toda la empresa</p>
                        {divider}
                        {menu.company.map((d) => dashboardLink(d, 'Toda la empresa'))}
                    </div>
                )}

                {menu && menu.unassigned.length > 0 && (
                    <div>
                        <p className={sectionClass}>Sin asignar</p>
                        {divider}
                        {menu.unassigned.map((d) => dashboardLink(d, 'Sin asignar'))}
                    </div>
                )}

                {menu && !hasAny && !collapsed && user?.role !== 'super_admin' && (
                    <p className="mt-3 px-2 text-xs leading-relaxed text-slate-500">
                        No tenés dashboards asignados. Pedile al administrador que te asigne a una división.
                    </p>
                )}

                {user?.role === 'super_admin' && (
                    <div>
                        <p className={sectionClass}>Administración</p>
                        {divider}
                        {ADMIN_LINKS.map((l) => (
                            <RailTooltip key={l.to} label={l.label} enabled={collapsed}>
                                <NavLink to={l.to} className={linkClass}>
                                    <span className="inline-flex h-6 w-6 shrink-0 items-center justify-center">
                                        <Icon name={l.icon} className="h-4 w-4" />
                                    </span>
                                    {!collapsed && <span>{l.label}</span>}
                                </NavLink>
                            </RailTooltip>
                        ))}
                    </div>
                )}
            </nav>

            <div className={`flex shrink-0 border-t border-slate-200 ${collapsed ? 'flex-col items-center gap-1 px-1.5 py-2' : 'items-center justify-between px-2 py-2'}`}>
                <button
                    type="button"
                    onClick={onToggle}
                    aria-expanded={!collapsed}
                    aria-label={collapsed ? 'Expandir menú' : 'Contraer menú'}
                    title={collapsed ? 'Expandir menú' : 'Contraer menú'}
                    className="flex h-8 w-8 items-center justify-center rounded text-slate-600 hover:bg-slate-200"
                >
                    <Icon name={collapsed ? 'chevron-right' : 'chevron-left'} className="h-4 w-4" />
                </button>
                <button
                    type="button"
                    onClick={() => void handleLogout()}
                    title="Cerrar sesión"
                    aria-label="Cerrar sesión"
                    className={`flex h-8 items-center justify-center gap-2 rounded text-sm text-slate-600 hover:bg-slate-200 ${collapsed ? 'w-8' : 'px-2'}`}
                >
                    <Icon name="logout" className="h-4 w-4" />
                    {!collapsed && <span>Cerrar sesión</span>}
                </button>
            </div>
        </aside>
    );
}
