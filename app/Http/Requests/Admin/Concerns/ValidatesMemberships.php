<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\Group;
use Illuminate\Validation\Validator;

/**
 * Reglas comunes de division_ids / group_ids y la comprobación de que cada grupo pertenece a
 * una división asignada.
 */
trait ValidatesMemberships
{
    protected function membershipRules(): array
    {
        return [
            'division_ids' => ['sometimes', 'array'],
            'division_ids.*' => ['uuid', 'exists:divisions,id'],
            'group_ids' => ['sometimes', 'array'],
            'group_ids.*' => ['uuid', 'exists:groups,id'],
        ];
    }

    protected function membershipMessages(): array
    {
        return [
            'division_ids.array' => 'Las divisiones deben enviarse como lista.',
            'division_ids.*.exists' => 'Una de las divisiones elegidas no existe.',
            'division_ids.*.uuid' => 'Una de las divisiones elegidas no es válida.',
            'group_ids.array' => 'Los grupos deben enviarse como lista.',
            'group_ids.*.exists' => 'Uno de los grupos elegidos no existe.',
            'group_ids.*.uuid' => 'Uno de los grupos elegidos no es válido.',
        ];
    }

    /** @param  list<string>  $effectiveDivisionIds  divisiones que quedarán asignadas */
    protected function assertGroupsBelongToDivisions(Validator $validator, array $effectiveDivisionIds): void
    {
        $groupIds = $this->input('group_ids');
        if (! is_array($groupIds) || $groupIds === []) {
            return;
        }

        $strays = Group::query()->with('division')->whereIn('id', $groupIds)->whereNotIn('division_id', $effectiveDivisionIds)->get();
        foreach ($strays as $group) {
            $validator->errors()->add('group_ids', "El grupo «{$group->name}» pertenece a la división «{$group->division->name}», que no está asignada al usuario.");
        }
    }
}
