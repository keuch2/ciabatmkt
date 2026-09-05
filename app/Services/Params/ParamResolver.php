<?php

namespace App\Services\Params;

use App\Models\Dashboard;
use App\Models\ParamValue;

/**
 * Resolución de tres niveles: override del usuario, valor base, default del manifiesto.
 * Itera SIEMPRE sobre los parámetros del manifiesto: la tabla sólo dice qué fue modificado.
 * Un valor guardado que ya no cumple el manifiesto vigente (cambió tipo o rango) se marca
 * como obsoleto y se cae al siguiente nivel.
 */
class ParamResolver
{
    public function __construct(private readonly ParamValueValidator $validator) {}

    /**
     * @return array<string, array{value: mixed, source: 'user'|'base'|'default', has_override: bool, stale: bool}>
     */
    public function resolve(Dashboard $dashboard, ?string $userId): array
    {
        $rows = ParamValue::query()
            ->where('dashboard_id', $dashboard->id)
            ->where(fn ($q) => $q->whereNull('user_id')->when($userId, fn ($q) => $q->orWhere('user_id', $userId)))
            ->get();

        $base = [];
        $user = [];
        foreach ($rows as $row) {
            if ($row->user_id === null) {
                $base[$row->param_id] = $row->value;
            } else {
                $user[$row->param_id] = $row->value;
            }
        }

        $resolved = [];
        foreach ($dashboard->manifestParams() as $param) {
            $id = $param['id'];
            $stale = false;

            foreach ([['user', $user], ['base', $base]] as [$source, $values]) {
                if (! array_key_exists($id, $values)) {
                    continue;
                }
                if ($this->validator->isValid($param, $values[$id])) {
                    $resolved[$id] = ['value' => $values[$id], 'source' => $source, 'has_override' => array_key_exists($id, $user), 'stale' => $stale];

                    continue 2;
                }
                $stale = true;
            }

            $resolved[$id] = ['value' => $param['default'], 'source' => 'default', 'has_override' => array_key_exists($id, $user), 'stale' => $stale];
        }

        return $resolved;
    }

    /** Sólo los valores efectivos, clave por param_id: lo que recibe el iframe. */
    public function values(Dashboard $dashboard, ?string $userId): array
    {
        return array_map(fn ($r) => $r['value'], $this->resolve($dashboard, $userId));
    }
}
