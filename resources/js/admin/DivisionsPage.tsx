import { useState, type FormEvent } from 'react';
import { ApiError } from '@/api/client';
import { createDivision, createGroup, deleteDivision, deleteGroup, listDivisions, updateDivision, updateGroup, type Division, type Group } from '@/api/divisions';
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
            <PageHeader title="Divisiones" description="Sectores de la empresa y sus grupos. Los usuarios se asignan desde Usuarios; los dashboards, al editar cada dashboard." />

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

function DivisionCard({
    division, canUp, canDown, onMove, onRename, onDelete, onAddGroup, onRenameGroup, onMoveGroup, onDeleteGroup,
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
}) {
    const [groupName, setGroupName] = useState('');

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

            <ul className="divide-y divide-slate-100">
                {division.groups.map((group, gi) => (
                    <li key={group.id} className="flex items-center justify-between gap-3 px-3 py-1.5 pl-6">
                        <div className="min-w-0">
                            <InlineName value={group.name} onSave={(name) => onRenameGroup(group, name)} className="text-sm text-slate-800" />
                            <span className="ml-2 text-xs text-slate-500">
                                {group.users_count ?? 0} usuario{group.users_count === 1 ? '' : 's'} · {group.dashboards_count ?? 0} dashboard{group.dashboards_count === 1 ? '' : 's'}
                            </span>
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
