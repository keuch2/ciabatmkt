import { Navigate, Outlet, useLocation } from 'react-router-dom';
import { useAuth } from './AuthProvider';
import { FullPageSpinner } from '@/ui/Spinner';

export function RequireAuth() {
    const { user, status } = useAuth();
    const location = useLocation();

    if (status === 'loading') return <FullPageSpinner />;
    if (!user) return <Navigate to="/login" replace state={{ from: location.pathname }} />;

    return <Outlet />;
}

export function RequireSuperAdmin() {
    const { user } = useAuth();

    if (user?.role !== 'super_admin') return <Navigate to="/" replace />;

    return <Outlet />;
}

/** Para /login y similares: si ya hay sesión, manda al inicio. */
export function RedirectIfAuthenticated() {
    const { user, status } = useAuth();

    if (status === 'loading') return <FullPageSpinner />;
    if (user) return <Navigate to="/" replace />;

    return <Outlet />;
}
