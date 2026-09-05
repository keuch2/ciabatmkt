import { useState, type FormEvent } from 'react';
import { useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { resetPassword } from '@/api/auth';
import { ApiError } from '@/api/client';
import { AuthLayout } from '@/layout/AuthLayout';
import { Alert } from '@/ui/Alert';
import { Button } from '@/ui/Button';
import { Field } from '@/ui/Field';
import { Input } from '@/ui/Input';

export function ResetPasswordPage() {
    const { token = '' } = useParams();
    const [search] = useSearchParams();
    const navigate = useNavigate();

    const [email, setEmail] = useState(search.get('email') ?? '');
    const [password, setPassword] = useState('');
    const [confirmation, setConfirmation] = useState('');
    const [errors, setErrors] = useState<Record<string, string | undefined>>({});
    const [message, setMessage] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    async function handleSubmit(event: FormEvent) {
        event.preventDefault();
        setLoading(true);
        setErrors({});
        setMessage(null);

        try {
            const result = await resetPassword({ token, email, password, password_confirmation: confirmation });
            navigate('/login', { replace: true, state: { notice: result.message } });
        } catch (e) {
            if (e instanceof ApiError) {
                setErrors({ email: e.fieldError('email'), password: e.fieldError('password'), token: e.fieldError('token') });
                if (!Object.keys(e.errors).length) setMessage(e.message);
            } else {
                setMessage('No se pudo conectar con el servidor.');
            }
        } finally {
            setLoading(false);
        }
    }

    return (
        <AuthLayout title="Nueva contraseña">
            <form onSubmit={handleSubmit} className="space-y-4" noValidate>
                {(message || errors.token) && <Alert tone="error">{message ?? errors.token}</Alert>}

                <Field label="Correo electrónico" htmlFor="email" error={errors.email}>
                    <Input
                        id="email"
                        type="email"
                        autoComplete="username"
                        value={email}
                        invalid={!!errors.email}
                        onChange={(e) => setEmail(e.target.value)}
                    />
                </Field>

                <Field label="Nueva contraseña" htmlFor="password" error={errors.password} hint="Mínimo 8 caracteres.">
                    <Input
                        id="password"
                        type="password"
                        autoComplete="new-password"
                        autoFocus
                        value={password}
                        invalid={!!errors.password}
                        onChange={(e) => setPassword(e.target.value)}
                    />
                </Field>

                <Field label="Repetir contraseña" htmlFor="password_confirmation">
                    <Input
                        id="password_confirmation"
                        type="password"
                        autoComplete="new-password"
                        value={confirmation}
                        onChange={(e) => setConfirmation(e.target.value)}
                    />
                </Field>

                <div className="flex justify-end">
                    <Button type="submit" loading={loading}>
                        Guardar contraseña
                    </Button>
                </div>
            </form>
        </AuthLayout>
    );
}
