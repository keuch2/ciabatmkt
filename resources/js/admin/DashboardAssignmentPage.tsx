import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ApiError } from '@/api/client';
import { adminListDashboards, updateDashboard } from '@/api/dashboards';
import { listDivisions } from '@/api/divisions';
import { useRequest } from '@/app/useRequest';
import { useMenu } from '@/menu/MenuProvider';
import { Alert } from '@/ui/Alert';
import { Button } from '@/ui/Button';
import { Checkbox } from '@/ui/Checkbox';
import { IconPicker } from '@/ui/IconPicker';
import { isIconKey, type IconKey } from '@/ui/icons';
import { PageHeader } from '@/ui/PageHeader';
import { Spinner } from '@/ui/Spinner';
import { AssignmentFields } from './AssignmentFields';

/** Quién ve un dashboard y con qué ícono. Se guarda sin volver a subir el archivo. */
export function DashboardAssignmentPage() {
    const { id = '' } = useParams();
    const navigate = useNavigate();
    const { reload: reloadMenu } = useMenu();
    const dashboards = useRequest(adminListDashboards, [id]);
    const divisions = useRequest(listDivisions, []);
    const dashboard = dashboards.data?.find((d) => d.id === id) ?? null;

    const [icon, setIcon] = useState<IconKey | null>(null);
    const [visibleToAll, setVisibleToAll] = useState(false);
    const [divisionIds, setDivisionIds] = useState<string[]>([]);
    const [groupIds, setGroupIds] = useState<string[]>([]);
    const [errors, setErrors] = useState<Record<string, string | undefined>>({});
    const [message, setMessage] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!dashboard) return;
        setIcon(isIconKey(dashboard.icon) ? dashboard.icon : null);
        setVisibleToAll(dashboard.visible_to_all);
        setDivisionIds((dashboard.divisions ?? []).map((d) => d.id));
        setGroupIds((dashboard.groups ?? []).map((g) => g.id));
    }, [dashboard]);

    async function save() {
        setSaving(true);
        setErrors({});
        setMessage(null);
        try {
            const result = await updateDashboard(id, { icon, visible_to_all: visibleToAll, division_ids: divisionIds, group_ids: groupIds });
            void reloadMenu();
            navigate('/admin/dashboards', { state: { notice: `Visibilidad de «${result.data.title}» guardada.` } });
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
                title={`Visibilidad · ${dashboard.title}`}
                description="Quién ve este dashboard y con qué ícono aparece en el menú."
                actions={
                    <Link to="/admin/dashboards" className="text-sm text-slate-600 underline-offset-2 hover:underline">
                        Volver
                    </Link>
                }
            />

            <div className="space-y-5 rounded border border-slate-200 bg-white p-4">
                {message && <Alert tone="error">{message}</Alert>}

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

                <div className="flex justify-end gap-2 border-t border-slate-200 pt-3">
                    <Button variant="secondary" onClick={() => navigate('/admin/dashboards')}>
                        Cancelar
                    </Button>
                    <Button onClick={() => void save()} loading={saving}>
                        Guardar visibilidad
                    </Button>
                </div>
            </div>
        </div>
    );
}
