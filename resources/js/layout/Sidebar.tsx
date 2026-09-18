import { NavLink, useNavigate } from 'react-router-dom';
import type { MenuDashboard } from '@/api/menu';
import { useAuth } from '@/auth/AuthProvider';
import { useMenu } from '@/menu/MenuProvider';
import { DashboardIcon, Icon, initials, type UiIconKey } from '@/ui/icons';
import { Spinner } from '@/ui/Spinner';
import logo from '@/assets/logociabay.png';

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
    const divisionClass = 'mt-3 mb-1 inline-block rounded-sm bg-[#1a9e3f] px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-white';
    // Contraído: la división es un cuadro verde con ícono y el nombre en el tooltip.
    const divisionHeader = (name: string) =>
        collapsed ? (
            <div className="mt-3 mb-1 flex justify-center" title={name} aria-label={name}>
                <span className="flex h-6 w-6 items-center justify-center rounded-sm bg-[#1a9e3f] text-white">
                    <Icon name="layers" className="h-3.5 w-3.5" />
                </span>
            </div>
        ) : (
            <p className={divisionClass}>{name}</p>
        );
    const divider = collapsed ? <div className="my-2 border-t border-slate-200" aria-hidden="true" /> : null;

    const dashboardLink = (d: MenuDashboard, context: string) => (
        <NavLink key={d.id} to={`/dashboards/${d.id}`} className={linkClass} title={collapsed ? `${d.title} · ${context}` : undefined}>
            <DashboardIcon icon={d.icon} title={d.title} className="h-6 w-6" />
            {!collapsed && (
                <span className="min-w-0 flex-1 truncate">
                    {d.title}
                    {!d.is_published && <span className="ml-1 rounded bg-amber-100 px-1 text-[10px] text-amber-800">borrador</span>}
                </span>
            )}
        </NavLink>
    );

    const hasAny = !!menu && (menu.divisions.length > 0 || menu.company.length > 0 || menu.unassigned.length > 0);

    return (
        <aside className={`flex min-h-screen flex-col border-r border-slate-200 bg-slate-50 ${collapsed ? 'w-14' : 'w-60'}`}>
            <div className={`border-b border-slate-200 ${collapsed ? 'px-2 py-3' : 'px-4 py-3'}`}>
                {collapsed ? (
                    <div className="flex h-8 items-center justify-center rounded bg-slate-800 text-xs font-bold text-white" title={`Ciabay · ${user?.name ?? ''}`}>
                        {initials(user?.name ?? 'C D')}
                    </div>
                ) : (
                    <img src={logo} alt="Ciabay" className="h-9 w-auto" title={user?.name} />
                )}
            </div>

            <nav className={`flex-1 overflow-y-auto overflow-x-hidden ${collapsed ? 'px-1.5 py-2' : 'px-2 py-2'}`} aria-label="Menú principal">
                <NavLink to="/" end className={linkClass} title={collapsed ? 'Inicio' : undefined}>
                    <span className="inline-flex h-6 w-6 shrink-0 items-center justify-center">
                        <Icon name="home" className="h-4 w-4" />
                    </span>
                    {!collapsed && <span>Inicio</span>}
                </NavLink>

                {loading && (
                    <div className="px-2 py-3">
                        <Spinner className="h-4 w-4" />
                    </div>
                )}
                {error && !collapsed && <p className="px-2 py-2 text-xs text-red-700">{error}</p>}

                {menu?.divisions.map((division) => (
                    <div key={division.id}>
                        {divisionHeader(division.name)}
                        {division.dashboards.map((d) => dashboardLink(d, division.name))}
                        {division.groups.map((group) => (
                            <div key={group.id} className={collapsed ? '' : 'ml-2 border-l border-slate-200 pl-1.5'}>
                                {!collapsed && <p className="mt-1.5 mb-0.5 px-2 text-[11px] font-medium text-slate-500">{group.name}</p>}
                                {group.dashboards.map((d) => dashboardLink(d, `${division.name} / ${group.name}`))}
                            </div>
                        ))}
                    </div>
                ))}

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
                            <NavLink key={l.to} to={l.to} className={linkClass} title={collapsed ? l.label : undefined}>
                                <span className="inline-flex h-6 w-6 shrink-0 items-center justify-center">
                                    <Icon name={l.icon} className="h-4 w-4" />
                                </span>
                                {!collapsed && <span>{l.label}</span>}
                            </NavLink>
                        ))}
                    </div>
                )}
            </nav>

            <div className={`flex border-t border-slate-200 ${collapsed ? 'flex-col items-center gap-1 px-1.5 py-2' : 'items-center justify-between px-2 py-2'}`}>
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
