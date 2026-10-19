import { Outlet, useLocation } from 'react-router-dom';
import { MenuProvider } from '@/menu/MenuProvider';
import { Sidebar } from './Sidebar';
import { useSidebarCollapsed } from './useSidebarCollapsed';

/** Layout autenticado: menú lateral colapsable y contenido. */
export function AppShell() {
    const [collapsed, setCollapsed] = useSidebarCollapsed();
    // Al ver un dashboard, el contenido va de borde a borde: el espacio lo maneja el propio dashboard.
    const fullBleed = /^\/(admin\/)?dashboards\/[^/]+(\/base)?\/?$/.test(useLocation().pathname);

    return (
        <MenuProvider>
            <div className={`grid min-h-screen transition-[grid-template-columns] duration-150 ${collapsed ? 'grid-cols-[56px_1fr]' : 'grid-cols-[240px_1fr]'}`}>
                <Sidebar collapsed={collapsed} onToggle={() => setCollapsed(!collapsed)} />
                <main className={`min-w-0 ${fullBleed ? '' : 'p-6'}`}>
                    <Outlet />
                </main>
            </div>
        </MenuProvider>
    );
}
