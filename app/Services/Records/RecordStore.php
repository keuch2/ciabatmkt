<?php

namespace App\Services\Records;

use App\Models\Dashboard;
use App\Models\DashboardRecord;
use App\Models\DashboardRecordHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Lectura y escritura de los registros compartidos de un dashboard. Toda validación vive acá:
 * colección declarada en el manifiesto, forma del id, tamaño del registro y cantidad por colección.
 */
class RecordStore
{
    public const ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_.:\-]{0,99}$/';

    public function __construct(
        private readonly int $maxRecords,
        private readonly int $maxBytes,
    ) {}

    /** @return array{records: list<array>, server_time: string} */
    public function list(Dashboard $dashboard, string $collection): array
    {
        $this->collectionDef($dashboard, $collection);

        $records = DashboardRecord::query()->with('editor')
            ->where('dashboard_id', $dashboard->id)->where('collection', $collection)
            ->orderBy('created_at')->orderBy('record_id')
            ->get()->map(fn (DashboardRecord $r) => $r->toRecordArray())->all();

        return ['records' => $records, 'server_time' => $this->serverTime()];
    }

    /**
     * Crea o reemplaza un registro. Con $expectedVersion, rechaza la escritura si otro usuario
     * cambió el registro desde que este cliente lo leyó (409).
     *
     * @throws ValidationException|RecordConflictException
     */
    public function put(Dashboard $dashboard, string $collection, string $recordId, mixed $data, ?int $expectedVersion, User $actor): array
    {
        $def = $this->collectionDef($dashboard, $collection);
        $this->assertRecordId($recordId);
        $json = $this->assertData($def, $recordId, $data);

        return DB::transaction(function () use ($dashboard, $collection, $recordId, $json, $expectedVersion, $actor, $def) {
            $current = DashboardRecord::query()
                ->where('dashboard_id', $dashboard->id)->where('collection', $collection)->where('record_id', $recordId)
                ->lockForUpdate()->first();

            if ($current === null) {
                $count = DashboardRecord::query()->where('dashboard_id', $dashboard->id)->where('collection', $collection)->count();
                if ($count >= $def['maxRecords']) {
                    throw ValidationException::withMessages(['data' => "La colección «{$collection}» alcanzó el máximo de {$def['maxRecords']} registros."]);
                }
                DB::table('dashboard_records')->insert([
                    'id' => (string) Str::uuid(), 'dashboard_id' => $dashboard->id, 'collection' => $collection, 'record_id' => $recordId,
                    'data' => $json, 'version' => 1, 'created_by' => $actor->id, 'updated_by' => $actor->id,
                ]);
            } else {
                if ($expectedVersion !== null && $expectedVersion !== $current->version) {
                    $current->load('editor');
                    throw new RecordConflictException($current->toRecordArray());
                }
                // Sin cambio real no se toca la fila: ni versión, ni updated_at, ni historial.
                if (json_encode($current->data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) === $json) {
                    return $this->find($dashboard, $collection, $recordId);
                }
                DB::table('dashboard_records')->where('id', $current->id)->update([
                    'data' => $json, 'version' => $current->version + 1, 'updated_by' => $actor->id, 'updated_at' => DB::raw('now(6)'),
                ]);
            }

            return $this->find($dashboard, $collection, $recordId);
        });
    }

    public function remove(Dashboard $dashboard, string $collection, string $recordId, User $actor): bool
    {
        $this->collectionDef($dashboard, $collection);

        return DB::transaction(function () use ($dashboard, $collection, $recordId, $actor) {
            $this->setActor($actor);

            return DB::table('dashboard_records')
                ->where('dashboard_id', $dashboard->id)->where('collection', $collection)->where('record_id', $recordId)
                ->delete() > 0;
        });
    }

    /**
     * Carga inicial: sólo inserta si la colección está vacía. Idempotente ante dos usuarios que
     * abren el dashboard a la vez (la restricción única descarta el duplicado).
     *
     * @param  list<array{id: string, data: mixed}>  $records
     */
    public function seed(Dashboard $dashboard, string $collection, array $records, User $actor): int
    {
        $def = $this->collectionDef($dashboard, $collection);
        $rows = $this->rowsFor($dashboard, $collection, $def, $records, $actor);

        return DB::transaction(function () use ($dashboard, $collection, $rows) {
            if (DashboardRecord::query()->where('dashboard_id', $dashboard->id)->where('collection', $collection)->exists()) {
                return 0;
            }
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('dashboard_records')->insertOrIgnore($chunk);
            }

            return count($rows);
        });
    }

    /** Reemplaza toda la colección (restaurar respaldo). Sólo super administrador; lo controla el endpoint. */
    public function replace(Dashboard $dashboard, string $collection, array $records, User $actor): int
    {
        $def = $this->collectionDef($dashboard, $collection);
        $rows = $this->rowsFor($dashboard, $collection, $def, $records, $actor);

        return DB::transaction(function () use ($dashboard, $collection, $rows, $actor) {
            $this->setActor($actor);
            DB::table('dashboard_records')->where('dashboard_id', $dashboard->id)->where('collection', $collection)->delete();
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('dashboard_records')->insert($chunk);
            }

            return count($rows);
        });
    }

    /**
     * Cambios posteriores a un cursor (server_time de una respuesta anterior), para la
     * sincronización entre usuarios. Sale del historial, así incluye los borrados.
     *
     * @return array{changed: list<array>, deleted: list<string>, server_time: string}
     */
    public function changesSince(Dashboard $dashboard, string $collection, string $since): array
    {
        $this->collectionDef($dashboard, $collection);
        $serverTime = $this->serverTime();

        $events = DashboardRecordHistory::query()
            ->where('dashboard_id', $dashboard->id)->where('collection', $collection)
            ->where('changed_at', '>', $since)->where('changed_at', '<=', $serverTime)
            ->orderBy('changed_at')
            ->get(['record_id', 'action']);

        $last = [];
        foreach ($events as $event) {
            $last[$event->record_id] = $event->action;
        }

        $changedIds = array_keys(array_filter($last, fn ($a) => $a !== 'delete'));
        $deleted = array_keys(array_filter($last, fn ($a) => $a === 'delete'));

        $changed = $changedIds === [] ? [] : DashboardRecord::query()->with('editor')
            ->where('dashboard_id', $dashboard->id)->where('collection', $collection)->whereIn('record_id', $changedIds)
            ->get()->map(fn (DashboardRecord $r) => $r->toRecordArray())->values()->all();

        return ['changed' => $changed, 'deleted' => array_values($deleted), 'server_time' => $serverTime];
    }

    /** @return array<string, mixed> definición de la colección con límites efectivos */
    public function collectionDef(Dashboard $dashboard, string $collection): array
    {
        foreach ($dashboard->manifestCollections() as $def) {
            if (($def['id'] ?? null) === $collection) {
                return $def + ['maxRecords' => $this->maxRecords, 'maxBytes' => $this->maxBytes];
            }
        }

        $known = implode(', ', array_map(fn ($c) => $c['id'], $dashboard->manifestCollections())) ?: '(ninguna)';

        throw ValidationException::withMessages([
            'collection' => "La colección «{$collection}» no está declarada en el manifiesto de este dashboard. Colecciones declaradas: {$known}.",
        ]);
    }

    private function find(Dashboard $dashboard, string $collection, string $recordId): array
    {
        return DashboardRecord::query()->with('editor')
            ->where('dashboard_id', $dashboard->id)->where('collection', $collection)->where('record_id', $recordId)
            ->firstOrFail()->toRecordArray();
    }

    private function rowsFor(Dashboard $dashboard, string $collection, array $def, array $records, User $actor): array
    {
        if (count($records) > $def['maxRecords']) {
            throw ValidationException::withMessages(['records' => "Son ".count($records)." registros y la colección «{$collection}» admite hasta {$def['maxRecords']}."]);
        }
        $rows = [];
        $seen = [];
        foreach ($records as $i => $record) {
            $id = is_array($record) ? ($record['id'] ?? null) : null;
            if (! is_string($id) || ! is_array($record) || ! array_key_exists('data', $record)) {
                throw ValidationException::withMessages(['records' => "El registro en la posición {$i} debe tener \"id\" (texto) y \"data\"."]);
            }
            $this->assertRecordId($id);
            if (isset($seen[$id])) {
                throw ValidationException::withMessages(['records' => "El id «{$id}» está repetido en los registros enviados."]);
            }
            $seen[$id] = true;
            $rows[] = [
                'id' => (string) Str::uuid(), 'dashboard_id' => $dashboard->id, 'collection' => $collection, 'record_id' => $id,
                'data' => $this->assertData($def, $id, $record['data']), 'version' => 1, 'created_by' => $actor->id, 'updated_by' => $actor->id,
            ];
        }

        return $rows;
    }

    private function assertRecordId(string $recordId): void
    {
        if (! preg_match(self::ID_PATTERN, $recordId)) {
            throw ValidationException::withMessages(['record_id' => "El id de registro «{$recordId}» no es válido: hasta 100 caracteres entre letras, números, guión, guión bajo, punto y dos puntos."]);
        }
    }

    /** Devuelve el JSON serializado del registro, validado en forma y tamaño. */
    private function assertData(array $def, string $recordId, mixed $data): string
    {
        if (! is_array($data)) {
            throw ValidationException::withMessages(['data' => "El registro «{$recordId}» debe ser un objeto o un arreglo JSON."]);
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) {
            throw ValidationException::withMessages(['data' => "El registro «{$recordId}» contiene valores que no se pueden serializar a JSON."]);
        }
        if (strlen($json) > $def['maxBytes']) {
            $kb = (int) ($def['maxBytes'] / 1024);
            throw ValidationException::withMessages(['data' => "El registro «{$recordId}» supera el tamaño máximo de {$kb} KB."]);
        }

        return $json;
    }

    private function setActor(User $actor): void
    {
        DB::statement('set @ciabay_actor_id = ?', [$actor->id]);
    }

    private function serverTime(): string
    {
        return (string) DB::selectOne('select now(6) as t')->t;
    }
}
