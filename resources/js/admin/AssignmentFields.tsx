import type { Division } from '@/api/divisions';
import { Checkbox } from '@/ui/Checkbox';

interface Props {
    divisions: Division[];
    divisionIds: string[];
    groupIds: string[];
    onChange: (next: { divisionIds: string[]; groupIds: string[] }) => void;
    /** user: los grupos se habilitan al marcar su división. dashboard: la división marcada vale por todos sus grupos. */
    mode: 'user' | 'dashboard';
    errors?: { division_ids?: string; group_ids?: string };
}

/** Lista anidada división → grupos con casillas, compartida por el formulario de usuario y la asignación de dashboards. */
export function AssignmentFields({ divisions, divisionIds, groupIds, onChange, mode, errors }: Props) {
    function toggleDivision(division: Division, checked: boolean) {
        const nextDivisions = checked ? [...new Set([...divisionIds, division.id])] : divisionIds.filter((id) => id !== division.id);
        const ownGroups = division.groups.map((g) => g.id);
        // Al quitar una división (usuario) o marcarla entera (dashboard), sus grupos dejan de tener sentido.
        const dropGroups = mode === 'user' ? !checked : checked;
        const nextGroups = dropGroups ? groupIds.filter((id) => !ownGroups.includes(id)) : groupIds;
        onChange({ divisionIds: nextDivisions, groupIds: nextGroups });
    }

    function toggleGroup(groupId: string, checked: boolean) {
        onChange({ divisionIds, groupIds: checked ? [...new Set([...groupIds, groupId])] : groupIds.filter((id) => id !== groupId) });
    }

    if (divisions.length === 0) {
        return <p className="text-sm text-slate-500">Todavía no hay divisiones. Crealas en Administración → Divisiones.</p>;
    }

    return (
        <div className="space-y-3">
            {divisions.map((division) => {
                const checked = divisionIds.includes(division.id);
                const groupsEnabled = mode === 'user' ? checked : !checked;
                return (
                    <div key={division.id} className="rounded border border-slate-200 bg-white px-3 py-2">
                        <Checkbox
                            label={<span className="font-medium text-slate-900">{division.name}</span>}
                            hint={mode === 'dashboard' ? 'Toda la división' : undefined}
                            checked={checked}
                            onChange={(e) => toggleDivision(division, e.target.checked)}
                        />
                        {division.groups.length > 0 && (
                            <div className="mt-1.5 ml-6 grid grid-cols-1 gap-1 sm:grid-cols-2">
                                {division.groups.map((group) => (
                                    <Checkbox
                                        key={group.id}
                                        label={group.name}
                                        checked={groupIds.includes(group.id)}
                                        disabled={!groupsEnabled}
                                        onChange={(e) => toggleGroup(group.id, e.target.checked)}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}
            {errors?.division_ids && <p className="text-xs text-red-700">{errors.division_ids}</p>}
            {errors?.group_ids && <p className="text-xs text-red-700">{errors.group_ids}</p>}
        </div>
    );
}
