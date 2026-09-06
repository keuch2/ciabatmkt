<?php

namespace App\Services\Params;

use App\Models\Dashboard;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Escribe un valor de parámetro (override personal o valor base) validando contra el manifiesto.
 * La base no impone el tipo porque el tipo es dinámico: toda la validación vive acá.
 */
class ParamWriter
{
    public function __construct(private readonly ParamValueValidator $validator) {}

    /**
     * @param  string|null  $userId  null = valor base; con valor = override de ese usuario.
     *
     * @throws ValidationException si el parámetro no existe o el valor no cumple el manifiesto (422).
     */
    public function write(Dashboard $dashboard, string $paramId, mixed $value, ?string $userId, User $actor): void
    {
        $param = $this->findParam($dashboard, $paramId);

        $error = $this->validator->validate($param, $value);
        if ($error !== null) {
            throw ValidationException::withMessages(['value' => $error]);
        }

        // Upsert sobre el índice único (dashboard_id, param_id, coalesce(user_id, nil)).
        DB::statement(<<<'SQL'
            insert into param_values (id, dashboard_id, param_id, user_id, value, updated_by, updated_at)
            values (?, ?, ?, ?, cast(? as json), ?, now())
            as new
            on duplicate key update
                value = new.value,
                updated_by = new.updated_by,
                updated_at = now()
        SQL, [
            (string) Str::uuid(),
            $dashboard->id,
            $paramId,
            $userId,
            json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION),
            $actor->id,
        ]);
    }

    /**
     * Borra el override (o el valor base si $userId es null). NO escribe el default: al borrar,
     * la resolución cae sola al siguiente nivel y sigue los cambios futuros del valor base.
     *
     * @return bool true si existía una fila y se borró.
     */
    public function remove(Dashboard $dashboard, string $paramId, ?string $userId, User $actor): bool
    {
        $this->findParam($dashboard, $paramId, allowOrphan: true);

        return DB::transaction(function () use ($dashboard, $paramId, $userId, $actor) {
            $this->setActor($actor);

            $deleted = DB::table('param_values')
                ->where('dashboard_id', $dashboard->id)
                ->where('param_id', $paramId)
                ->when($userId === null, fn ($q) => $q->whereNull('user_id'), fn ($q) => $q->where('user_id', $userId))
                ->delete();

            return $deleted > 0;
        });
    }

    /** Borra todos los overrides del usuario (o todos los valores base) de un dashboard. */
    public function removeAll(Dashboard $dashboard, ?string $userId, User $actor): int
    {
        return DB::transaction(function () use ($dashboard, $userId, $actor) {
            $this->setActor($actor);

            return DB::table('param_values')
                ->where('dashboard_id', $dashboard->id)
                ->when($userId === null, fn ($q) => $q->whereNull('user_id'), fn ($q) => $q->where('user_id', $userId))
                ->delete();
        });
    }

    /** El trigger de borrado lee esta variable de sesión para atribuir el cambio. */
    private function setActor(User $actor): void
    {
        DB::statement('set @ciabay_actor_id = ?', [$actor->id]);
    }

    /** @return array<string, mixed> */
    private function findParam(Dashboard $dashboard, string $paramId, bool $allowOrphan = false): array
    {
        foreach ($dashboard->manifestParams() as $param) {
            if (($param['id'] ?? null) === $paramId) {
                return $param;
            }
        }

        if ($allowOrphan) {
            return ['id' => $paramId];
        }

        $known = implode(', ', array_map(fn ($p) => $p['id'], $dashboard->manifestParams()));

        throw ValidationException::withMessages([
            'param_id' => "El parámetro «{$paramId}» no existe en la versión {$dashboard->version} de este dashboard. Parámetros disponibles: {$known}.",
        ]);
    }
}
