import { Link } from 'react-router-dom';

export function NotFoundPage() {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center gap-2 text-sm text-slate-600">
            <p className="text-2xl font-semibold text-slate-800">404</p>
            <p>La página que buscás no existe.</p>
            <Link to="/" className="text-slate-800 underline-offset-2 hover:underline">
                Ir al inicio
            </Link>
        </div>
    );
}
