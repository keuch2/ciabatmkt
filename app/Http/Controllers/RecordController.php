<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDashboards;
use App\Models\Dashboard;
use App\Services\Records\RecordStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Registros compartidos de un dashboard. Cualquier usuario autenticado lee, crea, cambia y
 * borra; el historial registra quién hizo cada cosa. Reemplazar toda una colección (restaurar un
 * respaldo) es sólo para super administradores.
 */
class RecordController extends Controller
{
    use ResolvesVisibleDashboards;

    public function __construct(private readonly RecordStore $store) {}

    public function index(Request $request, Dashboard $dashboard, string $collection): JsonResponse
    {
        $this->ensureVisible($dashboard, $request->user());

        return response()->json($this->store->list($dashboard, $collection));
    }

    public function changes(Request $request, Dashboard $dashboard, string $collection): JsonResponse
    {
        $this->ensureVisible($dashboard, $request->user());
        $since = (string) $request->query('since', '');
        if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(\.\d{1,6})?$/', $since)) {
            throw ValidationException::withMessages(['since' => 'El cursor "since" debe ser el server_time de una respuesta anterior.']);
        }

        return response()->json($this->store->changesSince($dashboard, $collection, $since));
    }

    public function update(Request $request, Dashboard $dashboard, string $collection, string $recordId): JsonResponse
    {
        $this->ensureVisible($dashboard, $request->user());
        $input = $request->validate([
            'data' => ['present'],
            'version' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ], [
            'data.present' => 'Enviá el campo "data" con el contenido del registro.',
            'version.integer' => 'El campo "version" debe ser un entero.',
        ]);

        $record = $this->store->put($dashboard, $collection, $recordId, $input['data'], $input['version'] ?? null, $request->user());

        return response()->json(['record' => $record]);
    }

    public function destroy(Request $request, Dashboard $dashboard, string $collection, string $recordId): JsonResponse
    {
        $this->ensureVisible($dashboard, $request->user());

        return response()->json(['deleted' => $this->store->remove($dashboard, $collection, $recordId, $request->user())]);
    }

    /** Datos iniciales que trae el archivo: se cargan sólo si la colección está vacía. */
    public function seed(Request $request, Dashboard $dashboard, string $collection): JsonResponse
    {
        $this->ensureVisible($dashboard, $request->user());
        $input = $request->validate(['records' => ['present', 'array']], ['records.present' => 'Enviá "records" con los registros iniciales.']);

        $seeded = $this->store->seed($dashboard, $collection, $input['records'], $request->user());

        return response()->json(['seeded' => $seeded] + $this->store->list($dashboard, $collection));
    }

    /** Restaurar un respaldo: reemplaza toda la colección. Sólo super administrador. */
    public function replace(Request $request, Dashboard $dashboard, string $collection): JsonResponse
    {
        $this->ensureVisible($dashboard, $request->user());
        if (! $request->user()->isSuperAdmin()) {
            throw new AccessDeniedHttpException('Sólo un super administrador puede reemplazar los datos de una colección (restaurar un respaldo).');
        }
        $input = $request->validate(['records' => ['present', 'array']], ['records.present' => 'Enviá "records" con los registros a restaurar.']);

        $count = $this->store->replace($dashboard, $collection, $input['records'], $request->user());

        return response()->json(['replaced' => $count] + $this->store->list($dashboard, $collection));
    }
}
