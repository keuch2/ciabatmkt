import { useState, type FormEvent } from 'react';
import { createUser, listUsers, updateUser, type UserPayload } from '@/api/admin';
import { ApiError } from '@/api/client';
import type { User } from '@/api/types';
import { useRequest } from '@/app/useRequest';
import { useAuth } from '@/auth/AuthProvider';
import { Alert } from '@/ui/Alert';
import { Button } from '@/ui/Button';
import { Field } from '@/ui/Field';
import { formatDateTime } from '@/ui/formatValue';
import { Input } from '@/ui/Input';
import { PageHeader } from '@/ui/PageHeader';
import { Select } from '@/ui/Select';
import { Spinner } from '@/ui/Spinner';

type Draft = UserPayload;

const EMPTY: Draft = { name: '', email: '', password: '', role: 'user', is_active: true };

export function UsersPage() {
    const { user: me } = useAuth();
    const { data, error, loading, reload } = useRequest(listUsers, []);
    const [editing, setEditing] = useState<User | 'new' | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

    return (
        <div>
            <PageHeader
                title="Usuarios"
                description="Los usuarios no se eliminan: se desactivan, para conservar su historial."
                actions={<Button onClick={() => setEditing('new')}>Nuevo usuario</Button>}
            />
            {notice && (
                <div className="mb-3">
                    <Alert tone="success">{notice}</Alert>
                </div>
            )}
            {loading && <Spinner />}
            {error && <Alert tone="error">{error}</Alert>}

            {editing && (
                <UserForm
                    initial={editing === 'new' ? EMPTY : { name: editing.name, email: editing.email, password: '', role: editing.role, is_active: editing.is_active }}
                    isNew={editing === 'new'}
                    isSelf={editing !== 'new' && editing.id === me?.id}
                    onCancel={() => setEditing(null)}
                    onSubmit={async (draft) => {
                        if (editing === 'new') {
                            const created = await createUser(draft);
                            setNotice(`Usuario «${created.name}» creado.`);
                        } else {
                            const payload: Partial<UserPayload> = { ...draft };
                            if (!draft.password) delete payload.password;
                            const updated = await updateUser(editing.id, payload);
                            setNotice(`Usuario «${updated.name}» actualizado.`);
                        }
                        setEditing(null);
                        reload();
                    }}
                />
            )}

            {data && (
                <div className="overflow-x-auto rounded border border-slate-200 bg-white">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="px-3 py-2">Nombre</th>
                                <th className="px-3 py-2">Correo</th>
                                <th className="px-3 py-2">Rol</th>
                                <th className="px-3 py-2">Estado</th>
                                <th className="px-3 py-2">Alta</th>
                                <th className="px-3 py-2 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {data.map((u) => (
                                <tr key={u.id} className={u.is_active ? 'hover:bg-slate-50' : 'text-slate-400 hover:bg-slate-50'}>
                                    <td className="px-3 py-2 font-medium">
                                        {u.name}
                                        {u.id === me?.id && <span className="ml-1 text-xs text-slate-400">(vos)</span>}
                                    </td>
                                    <td className="px-3 py-2">{u.email}</td>
                                    <td className="px-3 py-2">{u.role === 'super_admin' ? 'Super administrador' : 'Usuario'}</td>
                                    <td className="px-3 py-2">
                                        <span className={`rounded px-1.5 py-0.5 text-xs ${u.is_active ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-600'}`}>
                                            {u.is_active ? 'Activo' : 'Inactivo'}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2 text-xs text-slate-500">{formatDateTime(u.created_at)}</td>
                                    <td className="px-3 py-2 text-right">
                                        <Button variant="ghost" onClick={() => setEditing(u)}>
                                            Editar
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function UserForm({
    initial,
    isNew,
    isSelf,
    onCancel,
    onSubmit,
}: {
    initial: Draft;
    isNew: boolean;
    isSelf: boolean;
    onCancel: () => void;
    onSubmit: (draft: Draft) => Promise<void>;
}) {
    const [draft, setDraft] = useState<Draft>(initial);
    const [errors, setErrors] = useState<Record<string, string | undefined>>({});
    const [message, setMessage] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    function set<K extends keyof Draft>(key: K, value: Draft[K]) {
        setDraft((d) => ({ ...d, [key]: value }));
    }

    async function handleSubmit(event: FormEvent) {
        event.preventDefault();
        setSaving(true);
        setErrors({});
        setMessage(null);
        try {
            await onSubmit(draft);
        } catch (e) {
            if (e instanceof ApiError) {
                setErrors(Object.fromEntries(Object.entries(e.errors).map(([k, v]) => [k, v[0]])));
                if (!Object.keys(e.errors).length) setMessage(e.message);
            } else {
                setMessage('No se pudo conectar con el servidor.');
            }
        } finally {
            setSaving(false);
        }
    }

    return (
        <form onSubmit={handleSubmit} className="mb-4 space-y-3 rounded border border-slate-300 bg-white p-4" noValidate>
            <p className="text-sm font-semibold text-slate-900">{isNew ? 'Nuevo usuario' : `Editar «${initial.name}»`}</p>
            {message && <Alert tone="error">{message}</Alert>}
            <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                <Field label="Nombre" htmlFor="u-name" error={errors.name}>
                    <Input id="u-name" value={draft.name} invalid={!!errors.name} onChange={(e) => set('name', e.target.value)} autoFocus />
                </Field>
                <Field label="Correo electrónico" htmlFor="u-email" error={errors.email}>
                    <Input id="u-email" type="email" value={draft.email} invalid={!!errors.email} onChange={(e) => set('email', e.target.value)} />
                </Field>
                <Field
                    label={isNew ? 'Contraseña inicial' : 'Nueva contraseña'}
                    htmlFor="u-password"
                    error={errors.password}
                    hint={isNew ? 'Mínimo 8 caracteres. El usuario puede cambiarla con "Olvidé mi contraseña".' : 'Dejá vacío para no cambiarla.'}
                >
                    <Input id="u-password" type="password" autoComplete="new-password" value={draft.password ?? ''} invalid={!!errors.password} onChange={(e) => set('password', e.target.value)} />
                </Field>
                <Field label="Rol" htmlFor="u-role" error={errors.role}>
                    <Select id="u-role" value={draft.role} disabled={isSelf} onChange={(e) => set('role', e.target.value as Draft['role'])}>
                        <option value="user">Usuario</option>
                        <option value="super_admin">Super administrador</option>
                    </Select>
                </Field>
            </div>
            <label className={`flex items-center gap-2 text-sm ${isSelf ? 'text-slate-400' : 'text-slate-700'}`}>
                <input type="checkbox" checked={draft.is_active} disabled={isSelf} onChange={(e) => set('is_active', e.target.checked)} />
                Cuenta activa
                {isSelf && <span className="text-xs">(no podés desactivarte a vos mismo)</span>}
            </label>
            {errors.is_active && <p className="text-xs text-red-700">{errors.is_active}</p>}
            <div className="flex justify-end gap-2">
                <Button type="button" variant="secondary" onClick={onCancel}>
                    Cancelar
                </Button>
                <Button type="submit" loading={saving}>
                    {isNew ? 'Crear usuario' : 'Guardar cambios'}
                </Button>
            </div>
        </form>
    );
}
