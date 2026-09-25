<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDashboards;
use App\Models\Dashboard;
use App\Services\Records\RecordConflictException;
use App\Services\Records\RecordStore;
use App\Services\Records\WriteFailureLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Registros compartidos de un dashboard. Cualquier usuario autenticado lee, crea, cambia y
 * borra; el historial registra quién hizo cada cosa. Reemplazar toda una colección (restaurar un
 * respaldo) es sólo para super administradores.
 *
 * El cuerpo JSON se lee crudo (json_decode sin arreglos asociativos) para que un objeto vacío {}
 * llegue como objeto y no se convierta en []: los dashboards usan mapas por clave.
 */
class RecordController extends Controller
{
    use ResolvesVisibleDashboards;

    public function __construct(private readonly RecordStore $store, private readonly WriteFailureLog $failures) {}

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
        $body = $this->rawBody($request);
        if (! property_exists($body, 'data')) {
            $this->failures->record($dashboard, $collection, $recordId, $request->user(), 'put', 'invalid', 'Falta el campo "data".');
            throw ValidationException::withMessages(['data' => 'Enviá el campo "data" con el contenido del registro.']);
        }
        $version = $body->version ?? null;
        if ($version !== null && (! is_int($version) || $version < 1)) {
            throw ValidationException::withMessages(['version' => 'El campo "version" debe ser un entero.']);
        }

        try {
            $record = $this->store->put($dashboard, $collection, $recordId, $body->data, $version, $request->user());
        } catch (ValidationException $e) {
            $this->failures->record($dashboard, $collection, $recordId, $request->user(), 'put', 'invalid', $this->firstMessage($e), strlen($request->getContent()));
            throw $e;
        } catch (RecordConflictException $e) {
            $this->failures->record($dashboard, $collection, $recordId, $request->user(), 'put', 'conflict', $e->getMessage(), strlen($request->getContent()));
            throw $e;
        }
        if ($this->store->lastPutWasNoop) {
            $this->failures->record($dashboard, $collection, $recordId, $request->user(), 'put', 'noop', 'Guardado sin cambios: el contenido enviado es igual al ya guardado.', strlen($request->getContent()));
        }

        return response()->json(['record' => $record]);
    }

    public function destroy(Request $request, Dashboard $dashboard, string $collection, string $recordId): JsonResponse
    {
        $this->ensureVisible($dashboard, $request->user());

        try {
            return response()->json(['deleted' => $this->store->remove($dashboard, $collection, $recordId, $request->user())]);
        } catch (ValidationException $e) {
            $this->failures->record($dashboard, $collection, $recordId, $request->user(), 'remove', 'invalid', $this->firstMessage($e));
            throw $e;
        }
    }

    /** Datos iniciales que trae el archivo: se cargan sólo si la colección está vacía. */
    public function seed(Request $request, Dashboard $dashboard, string $collection): JsonResponse
    {
        $this->ensureVisible($dashboard, $request->user());
        $records = $this->recordsFromBody($request, 'Enviá "records" con los registros iniciales.');

        try {
            $seeded = $this->store->seed($dashboard, $collection, $records, $request->user());
        } catch (ValidationException $e) {
            $this->failures->record($dashboard, $collection, null, $request->user(), 'seed', 'invalid', $this->firstMessage($e), strlen($request->getContent()));
            throw $e;
        }

        return response()->json(['seeded' => $seeded] + $this->store->list($dashboard, $collection));
    }

    /** Restaurar un respaldo: reemplaza toda la colección. Sólo super administrador. */
    public function replace(Request $request, Dashboard $dashboard, string $collection): JsonResponse
    {
        $this->ensureVisible($dashboard, $request->user());
        if (! $request->user()->isSuperAdmin()) {
            $this->failures->record($dashboard, $collection, null, $request->user(), 'replace', 'forbidden', 'Reemplazo de colección intentado por un usuario que no es super administrador.');
            throw new AccessDeniedHttpException('Sólo un super administrador puede reemplazar los datos de una colección (restaurar un respaldo).');
        }
        $records = $this->recordsFromBody($request, 'Enviá "records" con los registros a restaurar.');

        try {
            $count = $this->store->replace($dashboard, $collection, $records, $request->user());
        } catch (ValidationException $e) {
            $this->failures->record($dashboard, $collection, null, $request->user(), 'replace', 'invalid', $this->firstMessage($e), strlen($request->getContent()));
            throw $e;
        }

        return response()->json(['replaced' => $count] + $this->store->list($dashboard, $collection));
    }

    private function rawBody(Request $request): object
    {
        $body = json_decode($request->getContent());
        if ($body === [] || $body === null && trim($request->getContent()) === '') {
            $body = new \stdClass;
        }
        if (! is_object($body)) {
            throw ValidationException::withMessages(['body' => 'El cuerpo de la petición debe ser un objeto JSON.']);
        }

        return $body;
    }

    /** @return list<object> */
    private function recordsFromBody(Request $request, string $missing): array
    {
        $body = $this->rawBody($request);
        if (! property_exists($body, 'records') || ! is_array($body->records)) {
            throw ValidationException::withMessages(['records' => $missing]);
        }

        return $body->records;
    }

    private function firstMessage(ValidationException $e): string
    {
        foreach ($e->errors() as $messages) {
            return (string) ($messages[0] ?? $e->getMessage());
        }

        return $e->getMessage();
    }
}
