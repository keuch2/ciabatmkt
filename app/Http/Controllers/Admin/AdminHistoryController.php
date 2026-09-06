<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\HistoryEntryResource;
use App\Models\Dashboard;
use App\Models\ParamValueHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Historial completo de un dashboard, filtrable por parámetro, usuario, nivel y fechas.
 */
class AdminHistoryController extends Controller
{
    public function __invoke(Request $request, Dashboard $dashboard): JsonResponse
    {
        $filters = $request->validate([
            'param_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'user_id' => ['sometimes', 'nullable', 'uuid'],
            'scope' => ['sometimes', 'nullable', Rule::in(['user', 'base'])],
            'action' => ['sometimes', 'nullable', Rule::in(['insert', 'update', 'delete'])],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:10', 'max:200'],
        ], [
            'user_id.uuid' => 'El filtro de usuario no es válido.',
            'scope.in' => 'El filtro "scope" debe ser "user" o "base".',
            'from.date' => 'La fecha "desde" no es válida.',
            'to.date' => 'La fecha "hasta" no es válida.',
        ]);

        $page = ParamValueHistory::query()
            ->with(['actor', 'user'])
            ->where('dashboard_id', $dashboard->id)
            ->when($filters['param_id'] ?? null, fn ($q, $v) => $q->where('param_id', $v))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when(($filters['scope'] ?? null) === 'base', fn ($q) => $q->whereNull('user_id'))
            ->when(($filters['scope'] ?? null) === 'user', fn ($q) => $q->whereNotNull('user_id'))
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('changed_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('changed_at', '<', date('Y-m-d', strtotime($v.' +1 day'))))
            ->orderByDesc('changed_at')
            ->paginate($filters['per_page'] ?? 50);

        $labels = [];
        foreach ($dashboard->manifestParams() as $param) {
            $labels[$param['id']] = $param['label'] ?? $param['id'];
        }

        return response()->json([
            'data' => $page->getCollection()->map(fn ($row) => (new HistoryEntryResource($row, $labels))->resolve($request))->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
