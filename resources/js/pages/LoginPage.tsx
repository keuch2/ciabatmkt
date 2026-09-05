import { useState, type FormEvent } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { ApiError } from '@/api/client';
import { useAuth } from '@/auth/AuthProvider';
import { AuthLayout } from '@/layout/AuthLayout';
import { Alert } from '@/ui/Alert';
import { Button } from '@/ui/Button';
import { Field } from '@/ui/Field';
import { Input } from '@/ui/Input';

export function LoginPage() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const from = (location.state as { from?: string } | null)?.from ?? '/';
    const notice = (location.state as { notice?: string } | null)?.notice;

    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [errors, setErrors] = useState<Record<string, string | undefined>>({});
    const [message, setMessage] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    async function handleSubmit(event: FormEvent) {
        event.preventDefault();
        setLoading(true);
        setErrors({});
        setMessage(null);

        try {
            await login(email, password);
            navigate(from, { replace: true });
        } catch (error) {
            if (error instanceof ApiError) {
                setErrors({ email: error.fieldError('email'), password: error.fieldError('password') });
                if (!Object.keys(error.errors).length) setMessage(error.message);
            } else {
                setMessage('No se pudo conectar con el servidor.');
            }
        } finally {
            setLoading(false);
        }
    }

    return (
        <AuthLayout title="Iniciar sesión">
            <form onSubmit={handleSubmit} className="space-y-4" noValidate>
                {notice && <Alert tone="success">{notice}</Alert>}
                {message && <Alert tone="error">{message}</Alert>}

                <Field label="Correo electrónico" htmlFor="email" error={errors.email}>
                    <Input
                        id="email"
                        type="email"
                        autoComplete="username"
                        autoFocus
                        value={email}
                        invalid={!!errors.email}
                        onChange={(e) => setEmail(e.target.value)}
                    />
                </Field>

                <Field label="Contraseña" htmlFor="password" error={errors.password}>
                    <Input
                        id="password"
                        type="password"
                        autoComplete="current-password"
                        value={password}
                        invalid={!!errors.password}
                        onChange={(e) => setPassword(e.target.value)}
                    />
                </Field>

                <div className="flex items-center justify-between">
                    <Link to="/forgot-password" className="text-xs text-slate-600 underline-offset-2 hover:underline">
                        Olvidé mi contraseña
                    </Link>
                    <Button type="submit" loading={loading}>
                        Ingresar
                    </Button>
                </div>
            </form>
        </AuthLayout>
    );
}
