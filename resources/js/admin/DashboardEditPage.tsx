import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ApiError } from '@/api/client';
import { adminListDashboards, updateDashboard } from '@/api/dashboards';
import { dashboardHtmlPath } from '@/api/admin';
import { withBase } from '@/app/basePath';
import { listDivisions } from '@/api/divisions';
import { useRequest } from '@/app/useRequest';
import { useMenu } from '@/menu/MenuProvider';
import { Alert } from '@/ui/Alert';
import { Button } from '@/ui/Button';
import { Checkbox } from '@/ui/Checkbox';
import { Field } from '@/ui/Field';
import { Input } from '@/ui/Input';
import { IconPicker } from '@/ui/IconPicker';
import { isIconKey, type IconKey } from '@/ui/icons';
import { PageHeader } from '@/ui/PageHeader';
import { Spinner } from '@/ui/Spinner';
import { AssignmentFields } from './AssignmentFields';

/** Edición de un dashboard: ícono, quién lo ve y publicación. El archivo se reemplaza aparte. */
export function DashboardEditPage() {
    const { id = '' } = useParams();
    const navigate = useNavigate();
    const { reload: reloadMenu } = useMenu();
    const dashboards = useRequest(adminListDashboards, [id]);
    const divisions = useRequest(listDivisions, []);
    const dashboard = dashboards.data?.find((d) => d.id === id) ?? null;

    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [icon, setIcon] = useState<IconKey | null>(null);
    const [visibleToAll, setVisibleToAll] = useState(false);
    const [published, setPublished] = useState(true);
    const [divisionIds, setDivisionIds] = useState<string[]>([]);
    const [groupIds, setGroupIds] = useState<string[]>([]);
    const [errors, setErrors] = useState<Record<string, string | undefined>>({});
    const [message, setMessage] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!dashboard) return;
        setTitle(dashboard.title);
        setDescription(dashboard.description ?? '');
        setIcon(isIconKey(dashboard.icon) ? dashboard.icon : null);
        setVisibleToAll(dashboard.visible_to_all);
        setPublished(dashboard.is_published);
        setDivisionIds((dashboard.divisions ?? []).map((d) => d.id));
        setGroupIds((dashboard.groups ?? []).map((g) => g.id));
    }, [dashboard]);

    async function save() {
        setSaving(true);
        setErrors({});
        setMessage(null);
        try {
            const result = await updateDashboard(id, { title: title.trim(), description: description.trim() || null, icon, visible_to_all: visibleToAll, division_ids: divisionIds, group_ids: groupIds, is_published: published });
            void reloadMenu();
            navigate('/admin/dashboards', { state: { notice: `«${result.data.title}» guardado.` } });
        } catch (e) {
            if (e instanceof ApiError) {
                setErrors(Object.fromEntries(Object.entries(e.errors).map(([k, v]) => [k, v[0]])));
                setMessage(Object.keys(e.errors).length ? null : e.message);
            } else {
                setMessage('No se pudo conectar con el servidor.');
            }
        } finally {
            setSaving(false);
        }
    }

    if (dashboards.loading || divisions.loading) return <Spinner />;
    if (dashboards.error || divisions.error) return <Alert tone="error">{dashboards.error ?? divisions.error}</Alert>;
    if (!dashboard) return <Alert tone="error">El dashboard no existe.</Alert>;

    const unassigned = !visibleToAll && divisionIds.length === 0 && groupIds.length === 0;

    return (
        <div className="max-w-3xl">
            <PageHeader
                title={`Editar · ${dashboard.title}`}
                description={`Versión ${dashboard.version} · id ${dashboard.slug}`}
                actions={
                    <Link to="/admin/dashboards" className="text-sm text-slate-600 underline-offset-2 hover:underline">
                        Volver
                    </Link>
                }
            />

            <div className="space-y-5 rounded border border-slate-200 bg-white p-4">
                {message && <Alert tone="error">{message}</Alert>}

                <section className="space-y-3">
                    <Field label="Nombre" htmlFor="d-title" error={errors.title} hint="Es el que ven los usuarios en el menú y en el inicio. Se conserva aunque subas una versión nueva del archivo.">
                        <Input id="d-title" value={title} maxLength={150} invalid={!!errors.title} onChange={(e) => setTitle(e.target.value)} />
                    </Field>
                    <Field label="Descripción" htmlFor="d-description" error={errors.description} hint={`Opcional. Aparece en la tarjeta del inicio y en la cabecera del dashboard. ${description.length}/500`}>
                        <textarea
                            id="d-description"
                            value={description}
                            maxLength={500}
                            rows={3}
                            onChange={(e) => setDescription(e.target.value)}
                            className="w-full rounded border border-slate-300 bg-white px-2 py-1.5 text-sm text-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </Field>
                </section>

                <section>
                    <p className="mb-2 text-sm font-medium text-slate-900">Ícono en el menú</p>
                    <IconPicker value={icon} onChange={setIcon} />
                    {errors.icon && <p className="mt-1 text-xs text-red-700">{errors.icon}</p>}
                </section>

                <section>
                    <p className="mb-2 text-sm font-medium text-slate-900">Quién lo ve</p>
                    <Checkbox
                        label="Toda la empresa"
                        hint="Cualquier usuario activo, sin importar su división."
                        checked={visibleToAll}
                        onChange={(e) => setVisibleToAll(e.target.checked)}
                        className="mb-3"
                    />
                    <AssignmentFields
                        divisions={divisions.data ?? []}
                        divisionIds={divisionIds}
                        groupIds={groupIds}
                        mode="dashboard"
                        errors={{ division_ids: errors.division_ids, group_ids: errors.group_ids }}
                        onChange={(next) => {
                            setDivisionIds(next.divisionIds);
                            setGroupIds(next.groupIds);
                        }}
                    />
                    {unassigned && (
                        <div className="mt-3">
                            <Alert tone="info">Sin asignación, sólo los super administradores lo verán.</Alert>
                        </div>
                    )}
                </section>

                <section>
                    <p className="mb-2 text-sm font-medium text-slate-900">Publicación</p>
                    <Checkbox label="Publicado" hint="Sin publicar queda como borrador, visible sólo para super administradores." checked={published} onChange={(e) => setPublished(e.target.checked)} />
                </section>

                <section className="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    <a href={withBase(dashboardHtmlPath(id))} download className="underline">
                        Descargar el HTML vigente
                    </a>{' '}
                    (versión {dashboard.version}). Para reemplazarlo por una versión nueva, usá{' '}
                    <Link to={`/admin/dashboards/${id}/update`} className="underline">
                        Actualizar archivo
                    </Link>
                    . La asignación y el ícono se conservan.
                </section>

                <div className="flex justify-end gap-2 border-t border-slate-200 pt-3">
                    <Button variant="secondary" onClick={() => navigate('/admin/dashboards')}>
                        Cancelar
                    </Button>
                    <Button onClick={() => void save()} loading={saving} disabled={!title.trim()}>
                        Guardar cambios
                    </Button>
                </div>
            </div>
        </div>
    );
}
