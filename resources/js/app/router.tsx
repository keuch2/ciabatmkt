import { createBrowserRouter } from 'react-router-dom';
import { BASE_PATH } from './basePath';
import { AdminDashboardsPage } from '@/admin/AdminDashboardsPage';
import { DashboardUploadPage } from '@/admin/DashboardUploadPage';
import { RedirectIfAuthenticated, RequireAuth, RequireSuperAdmin } from '@/auth/RequireAuth';
import { AppShell } from '@/layout/AppShell';
import { AdminPlaceholderPage } from '@/pages/AdminPlaceholderPage';
import { DashboardListPage } from '@/pages/DashboardListPage';
import { DashboardPage } from '@/pages/DashboardPage';
import { ForgotPasswordPage } from '@/pages/ForgotPasswordPage';
import { LoginPage } from '@/pages/LoginPage';
import { NotFoundPage } from '@/pages/NotFoundPage';
import { ResetPasswordPage } from '@/pages/ResetPasswordPage';

export const router = createBrowserRouter(
    [
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
                        { path: '/dashboards/:id', element: <DashboardPage /> },
                        {
                            path: '/admin',
                            element: <RequireSuperAdmin />,
                            children: [
                                { path: 'dashboards', element: <AdminDashboardsPage /> },
                                { path: 'dashboards/new', element: <DashboardUploadPage /> },
                                { path: 'dashboards/:id/update', element: <DashboardUploadPage /> },
                                { path: 'dashboards/:id/base', element: <DashboardPage scope="base" /> },
                                { path: 'users', element: <AdminPlaceholderPage title="Usuarios" /> },
                                { path: 'history', element: <AdminPlaceholderPage title="Historial" /> },
                            ],
                        },
                    ],
                },
            ],
        },
        { path: '*', element: <NotFoundPage /> },
    ],
    { basename: BASE_PATH || undefined },
);
