import { useState, type FormEvent } from 'react';
import { ApiError } from '@/api/client';
import { adminListDashboards, updateDashboard, type DashboardSummary } from '@/api/dashboards';
import { createDivision, createGroup, deleteDivision, deleteGroup, listDivisions, updateDivision, updateGroup, type Division, type Group } from '@/api/divisions';
import { DashboardIcon } from '@/ui/icons';
import { Select } from '@/ui/Select';
import { useRequest } from '@/app/useRequest';
import { useMenu } from '@/menu/MenuProvider';
import { Alert } from '@/ui/Alert';
import { Button } from '@/ui/Button';
import { Input } from '@/ui/Input';
import { PageHeader } from '@/ui/PageHeader';
import { Spinner } from '@/ui/Spinner';

function errorMessage(e: unknown): string {
    if (e instanceof ApiError) {
        const first = Object.values(e.errors)[0]?.[0];
        return first ?? e.message;
    }
    return 'No se pudo conectar con el servidor.';
}

/** Divisiones (sectores de negocio) con sus grupos: alta, renombrado, orden y baja. */
export function DivisionsPage() {
    const { data, error, loading, reload } = useRequest(listDivisions, []);
    const dashboards = useRequest(adminListDashboards, []);
    const { reload: reloadMenu } = useMenu();
    const [notice, setNotice] = useState<string | null>(null);
    const [actionError, setActionError] = useState<string | null>(null);
    const [newName, setNewName] = useState('');
    const [busy, setBusy] = useState(false);

    async function run(action: () => Promise<unknown>, done: string) {
        setBusy(true);
        setActionError(null);
        try {
            await action();
            setNotice(done);
            reload();
            dashboards.reload();
            void reloadMenu();
        } catch (e) {
            setActionError(errorMessage(e));
        } finally {
            setBusy(false);
        }
    }

    async function addDivision(event: FormEvent) {
        event.preventDefault();
        const name = newName.trim();
        if (!name) return;
        await run(() => createDivision({ name, sort_order: (data?.length ?? 0) }), `División «${name}» creada.`);
        setNewName('');
    }

    function move(list: { id: string; sort_order: number }[], index: number, dir: -1 | 1, save: (id: string, sort_order: number) => Promise<unknown>) {
        const other = index + dir;
        if (other < 0 || other >= list.length) return;
        const a = list[index];
        const b = list[other];
        void run(() => Promise.all([save(a.id, other), save(b.id, index)]), 'Orden actualizado.');
    }

    return (
        <div className="max-w-5xl">
            <PageHeader title="Divisiones" description="Sectores de la empresa y sus grupos. Acá se define qué dashboards ve cada división o grupo; los usuarios se asignan desde Usuarios." />

            {notice && (
                <div className="mb-3">
                    <Alert tone="success">{notice}</Alert>
                </div>
            )}
            {actionError && (
                <div className="mb-3">
                    <Alert tone="error">{actionError}</Alert>
                </div>
            )}

            <form onSubmit={addDivision} className="mb-4 flex items-end gap-2 rounded border border-slate-200 bg-white p-3">
                <label className="flex-1 text-xs text-slate-600">
                    Nueva división
                    <Input value={newName} placeholder="Ej. Comercial" onChange={(e) => setNewName(e.target.value)} />
                </label>
                <Button type="submit" loading={busy} disabled={!newName.trim()}>
                    Crear división
                </Button>
            </form>

            {loading && <Spinner />}
            {error && <Alert tone="error">{error}</Alert>}

            {data && data.length === 0 && (
                <div className="rounded border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">
                    Todavía no hay divisiones.
                </div>
            )}

            <div className="space-y-3">
                {data?.map((division, index) => (
                    <DivisionCard
                        key={division.id}
                        division={division}
                        canUp={index > 0}
                        canDown={index < data.length - 1}
                        onMove={(dir) => move(data, index, dir, (id, sort_order) => updateDivision(id, { sort_order }))}
                        onRename={(name) => run(() => updateDivision(division.id, { name }), `División renombrada a «${name}».`)}
                        onDelete={() => {
                            if (!window.confirm(`¿Eliminar la división «${division.name}» y sus grupos? Los usuarios pierden esa pertenencia.`)) return;
                            void run(() => deleteDivision(division.id), `División «${division.name}» eliminada.`);
                        }}
                        onAddGroup={(name) => run(() => createGroup(division.id, { name, sort_order: division.groups.length }), `Grupo «${name}» creado.`)}
                        onRenameGroup={(g, name) => run(() => updateGroup(division.id, g.id, { name }), `Grupo renombrado a «${name}».`)}
                        onMoveGroup={(gi, dir) => move(division.groups, gi, dir, (id, sort_order) => updateGroup(division.id, id, { sort_order }))}
                        onDeleteGroup={(g) => {
                            if (!window.confirm(`¿Eliminar el grupo «${g.name}»? Los usuarios pierden esa pertenencia.`)) return;
                            void run(() => deleteGroup(division.id, g.id), `Grupo «${g.name}» eliminado.`);
                        }}
                        dashboards={dashboards.data ?? []}
                        onAssignDivision={(dash, on) => {
                            const ids = (dash.divisions ?? []).map((x) => x.id).filter((x) => x !== division.id);
                            void run(() => updateDashboard(dash.id, { division_ids: on ? [...ids, division.id] : ids }), on ? `«${dash.title}» asignado a ${division.name}.` : `«${dash.title}» quitado de ${division.name}.`);
                        }}
                        onAssignGroup={(g, dash, on) => {
                            const ids = (dash.groups ?? []).map((x) => x.id).filter((x) => x !== g.id);
                            void run(() => updateDashboard(dash.id, { group_ids: on ? [...ids, g.id] : ids }), on ? `«${dash.title}» asignado al grupo ${g.name}.` : `«${dash.title}» quitado del grupo ${g.name}.`);
                        }}
                    />
                ))}
            </div>
        </div>
    );
}

function InlineName({ value, onSave, className = '' }: { value: string; onSave: (name: string) => void; className?: string }) {
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(value);
    if (!editing) {
        return (
            <button type="button" onClick={() => { setDraft(value); setEditing(true); }} className={`text-left hover:underline ${className}`} title="Renombrar">
                {value}
            </button>
        );
    }
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                if (draft.trim() && draft.trim() !== value) onSave(draft.trim());
                setEditing(false);
            }}
            className="flex items-center gap-1"
        >
            <Input value={draft} autoFocus onChange={(e) => setDraft(e.target.value)} className="h-7 max-w-xs" />
            <Button type="submit" variant="secondary">
                Guardar
            </Button>
            <Button type="button" variant="ghost" onClick={() => setEditing(false)}>
                Cancelar
            </Button>
        </form>
    );
}

/** Lista de dashboards asignados a una división o grupo, con alta y baja. */
function AssignedDashboards({ assigned, available, onAdd, onRemove, addLabel }: {
    assigned: DashboardSummary[];
    available: DashboardSummary[];
    onAdd: (dash: DashboardSummary) => void;
    onRemove: (dash: DashboardSummary) => void;
    addLabel: string;
}) {
    return (
        <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
            {assigned.map((d) => (
                <span key={d.id} className="inline-flex items-center gap-1 rounded border border-slate-200 bg-slate-50 py-0.5 pr-1 pl-1.5 text-xs text-slate-800">
                    <DashboardIcon icon={d.icon} title={d.title} className="h-4 w-4 text-[8px]" />
                    {d.title}
                    <button type="button" onClick={() => onRemove(d)} className="ml-0.5 rounded px-1 text-slate-400 hover:bg-slate-200 hover:text-slate-800" title="Quitar" aria-label={`Quitar ${d.title}`}>
                        ×
                    </button>
                </span>
            ))}
            {available.length > 0 ? (
                <Select
                    value=""
                    aria-label={addLabel}
                    onChange={(e) => {
                        const dash = available.find((d) => d.id === e.target.value);
                        if (dash) onAdd(dash);
                    }}
                    className="h-7 w-auto max-w-xs text-xs"
                >
                    <option value="">{addLabel}</option>
                    {available.map((d) => (
                        <option key={d.id} value={d.id}>
                            {d.title}
                        </option>
                    ))}
                </Select>
            ) : (
                assigned.length === 0 && <span className="text-xs text-slate-400">Sin dashboards.</span>
            )}
        </div>
    );
}

function DivisionCard({
    division, canUp, canDown, onMove, onRename, onDelete, onAddGroup, onRenameGroup, onMoveGroup, onDeleteGroup, dashboards, onAssignDivision, onAssignGroup,
}: {
    division: Division;
    canUp: boolean;
    canDown: boolean;
    onMove: (dir: -1 | 1) => void;
    onRename: (name: string) => void;
    onDelete: () => void;
    onAddGroup: (name: string) => Promise<void>;
    onRenameGroup: (group: Group, name: string) => void;
    onMoveGroup: (index: number, dir: -1 | 1) => void;
    onDeleteGroup: (group: Group) => void;
    dashboards: DashboardSummary[];
    onAssignDivision: (dash: DashboardSummary, on: boolean) => void;
    onAssignGroup: (group: Group, dash: DashboardSummary, on: boolean) => void;
}) {
    const [groupName, setGroupName] = useState('');
    const inDivision = dashboards.filter((d) => (d.divisions ?? []).some((x) => x.id === division.id));
    const notInDivision = dashboards.filter((d) => !(d.divisions ?? []).some((x) => x.id === division.id));

    return (
        <section className="rounded border border-slate-200 bg-white">
            <div className="flex items-center justify-between gap-3 border-b border-slate-200 px-3 py-2">
                <div className="min-w-0">
                    <InlineName value={division.name} onSave={onRename} className="text-sm font-semibold text-slate-900" />
                    <p className="text-xs text-slate-500">
                        {division.users_count ?? 0} usuario{division.users_count === 1 ? '' : 's'} · {division.dashboards_count ?? 0} dashboard
                        {division.dashboards_count === 1 ? '' : 's'} directo{division.dashboards_count === 1 ? '' : 's'}
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-1">
                    <Button variant="ghost" disabled={!canUp} onClick={() => onMove(-1)} title="Subir">
                        ↑
                    </Button>
                    <Button variant="ghost" disabled={!canDown} onClick={() => onMove(1)} title="Bajar">
                        ↓
                    </Button>
                    <Button variant="ghost" className="text-red-700" onClick={onDelete}>
                        Eliminar
                    </Button>
                </div>
            </div>

            <div className="border-b border-slate-100 px-3 py-2">
                <p className="text-xs font-medium text-slate-700">Dashboards de toda la división</p>
                <AssignedDashboards
                    assigned={inDivision}
                    available={notInDivision}
                    addLabel="Asignar dashboard a la división…"
                    onAdd={(d) => onAssignDivision(d, true)}
                    onRemove={(d) => onAssignDivision(d, false)}
                />
            </div>

            <ul className="divide-y divide-slate-100">
                {division.groups.map((group, gi) => (
                    <li key={group.id} className="flex items-start justify-between gap-3 px-3 py-1.5 pl-6">
                        <div className="min-w-0 flex-1">
                            <InlineName value={group.name} onSave={(name) => onRenameGroup(group, name)} className="text-sm text-slate-800" />
                            <span className="ml-2 text-xs text-slate-500">
                                {group.users_count ?? 0} usuario{group.users_count === 1 ? '' : 's'}
                            </span>
                            <AssignedDashboards
                                assigned={dashboards.filter((d) => (d.groups ?? []).some((x) => x.id === group.id))}
                                available={dashboards.filter((d) => !(d.groups ?? []).some((x) => x.id === group.id) && !(d.divisions ?? []).some((x) => x.id === division.id))}
                                addLabel="Asignar dashboard al grupo…"
                                onAdd={(d) => onAssignGroup(group, d, true)}
                                onRemove={(d) => onAssignGroup(group, d, false)}
                            />
                        </div>
                        <div className="flex shrink-0 items-center gap-1">
                            <Button variant="ghost" disabled={gi === 0} onClick={() => onMoveGroup(gi, -1)} title="Subir">
                                ↑
                            </Button>
                            <Button variant="ghost" disabled={gi === division.groups.length - 1} onClick={() => onMoveGroup(gi, 1)} title="Bajar">
                                ↓
                            </Button>
                            <Button variant="ghost" className="text-red-700" onClick={() => onDeleteGroup(group)}>
                                Eliminar
                            </Button>
                        </div>
                    </li>
                ))}
                {division.groups.length === 0 && <li className="px-3 py-2 pl-6 text-xs text-slate-500">Sin grupos.</li>}
            </ul>

            <form
                onSubmit={async (e) => {
                    e.preventDefault();
                    const name = groupName.trim();
                    if (!name) return;
                    await onAddGroup(name);
                    setGroupName('');
                }}
                className="flex items-center gap-2 border-t border-slate-100 px-3 py-2 pl-6"
            >
                <Input value={groupName} placeholder="Nuevo grupo en esta división" onChange={(e) => setGroupName(e.target.value)} className="max-w-xs" />
                <Button type="submit" variant="secondary" disabled={!groupName.trim()}>
                    Agregar grupo
                </Button>
            </form>
        </section>
    );
}
