import { Outlet } from 'react-router-dom';
import { MenuProvider } from '@/menu/MenuProvider';
import { Sidebar } from './Sidebar';
import { useSidebarCollapsed } from './useSidebarCollapsed';

/** Layout autenticado: menú lateral colapsable y contenido. */
export function AppShell() {
    const [collapsed, setCollapsed] = useSidebarCollapsed();

    return (
        <MenuProvider>
            <div className={`grid min-h-screen transition-[grid-template-columns] duration-150 ${collapsed ? 'grid-cols-[56px_1fr]' : 'grid-cols-[240px_1fr]'}`}>
                <Sidebar collapsed={collapsed} onToggle={() => setCollapsed(!collapsed)} />
                <main className="min-w-0 p-6">
                    <Outlet />
                </main>
            </div>
        </MenuProvider>
    );
}
