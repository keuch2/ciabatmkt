import { useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { forgotPassword } from '@/api/auth';
import { ApiError } from '@/api/client';
import { AuthLayout } from '@/layout/AuthLayout';
import { Alert } from '@/ui/Alert';
import { Button } from '@/ui/Button';
import { Field } from '@/ui/Field';
import { Input } from '@/ui/Input';

export function ForgotPasswordPage() {
    const [email, setEmail] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [done, setDone] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    async function handleSubmit(event: FormEvent) {
        event.preventDefault();
        setLoading(true);
        setError(null);

        try {
            const result = await forgotPassword(email);
            setDone(result.message);
        } catch (e) {
            setError(e instanceof ApiError ? (e.fieldError('email') ?? e.message) : 'No se pudo conectar con el servidor.');
        } finally {
            setLoading(false);
        }
    }

    return (
        <AuthLayout title="Restablecer contraseña">
            {done ? (
                <div className="space-y-4">
                    <Alert tone="success">{done}</Alert>
                    <Link to="/login" className="text-sm text-slate-700 underline-offset-2 hover:underline">
                        Volver al inicio de sesión
                    </Link>
                </div>
            ) : (
                <form onSubmit={handleSubmit} className="space-y-4" noValidate>
                    <p className="text-sm text-slate-600">
                        Ingresá tu correo y te enviamos un enlace para elegir una contraseña nueva.
                    </p>
                    <Field label="Correo electrónico" htmlFor="email" error={error ?? undefined}>
                        <Input
                            id="email"
                            type="email"
                            autoComplete="username"
                            autoFocus
                            value={email}
                            invalid={!!error}
                            onChange={(e) => setEmail(e.target.value)}
                        />
                    </Field>
                    <div className="flex items-center justify-between">
                        <Link to="/login" className="text-xs text-slate-600 underline-offset-2 hover:underline">
                            Volver
                        </Link>
                        <Button type="submit" loading={loading}>
                            Enviar enlace
                        </Button>
                    </div>
                </form>
            )}
        </AuthLayout>
    );
}
