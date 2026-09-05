import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { useAuth } from '@/auth/AuthProvider';
import { Button } from '@/ui/Button';

const linkClass = ({ isActive }: { isActive: boolean }) =>
    `block rounded px-2.5 py-1.5 text-sm ${isActive ? 'bg-slate-800 text-white' : 'text-slate-700 hover:bg-slate-200'}`;

export function AppShell() {
    const { user, logout } = useAuth();
    const navigate = useNavigate();

    async function handleLogout() {
        await logout();
        navigate('/login', { replace: true });
    }

    return (
        <div className="grid min-h-screen grid-cols-[220px_1fr]">
            <aside className="flex flex-col border-r border-slate-200 bg-slate-50">
                <div className="border-b border-slate-200 px-4 py-3">
                    <p className="text-sm font-semibold text-slate-900">Ciabay Dashboards</p>
                    <p className="truncate text-xs text-slate-500" title={user?.email}>
                        {user?.name}
                    </p>
                </div>

                <nav className="flex-1 space-y-4 p-2">
                    <div className="space-y-0.5">
                        <NavLink to="/" end className={linkClass}>
                            Dashboards
                        </NavLink>
                    </div>

                    {user?.role === 'super_admin' && (
                        <div className="space-y-0.5">
                            <p className="px-2.5 pb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                Administración
                            </p>
                            <NavLink to="/admin/dashboards" className={linkClass}>
                                Dashboards
                            </NavLink>
                            <NavLink to="/admin/users" className={linkClass}>
                                Usuarios
                            </NavLink>
                            <NavLink to="/admin/history" className={linkClass}>
                                Historial
                            </NavLink>
                        </div>
                    )}
                </nav>

                <div className="border-t border-slate-200 p-2">
                    <Button variant="ghost" className="w-full justify-start" onClick={handleLogout}>
                        Cerrar sesión
                    </Button>
                </div>
            </aside>

            <main className="min-w-0 p-6">
                <Outlet />
            </main>
        </div>
    );
}
