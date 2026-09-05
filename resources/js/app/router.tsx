import { createBrowserRouter } from 'react-router-dom';
import { RedirectIfAuthenticated, RequireAuth, RequireSuperAdmin } from '@/auth/RequireAuth';
import { AppShell } from '@/layout/AppShell';
import { AdminPlaceholderPage } from '@/pages/AdminPlaceholderPage';
import { DashboardListPage } from '@/pages/DashboardListPage';
import { ForgotPasswordPage } from '@/pages/ForgotPasswordPage';
import { LoginPage } from '@/pages/LoginPage';
import { NotFoundPage } from '@/pages/NotFoundPage';
import { ResetPasswordPage } from '@/pages/ResetPasswordPage';

export const router = createBrowserRouter([
    {
        element: <RedirectIfAuthenticated />,
        children: [
            { path: '/login', element: <LoginPage /> },
            { path: '/forgot-password', element: <ForgotPasswordPage /> },
            { path: '/reset-password/:token', element: <ResetPasswordPage /> },
        ],
    },
    {
        element: <RequireAuth />,
        children: [
            {
                element: <AppShell />,
                children: [
                    { path: '/', element: <DashboardListPage /> },
                    {
                        path: '/admin',
                        element: <RequireSuperAdmin />,
                        children: [
                            { path: 'dashboards', element: <AdminPlaceholderPage title="Dashboards" /> },
                            { path: 'users', element: <AdminPlaceholderPage title="Usuarios" /> },
                            { path: 'history', element: <AdminPlaceholderPage title="Historial" /> },
                        ],
                    },
                ],
            },
        ],
    },
    { path: '*', element: <NotFoundPage /> },
]);
