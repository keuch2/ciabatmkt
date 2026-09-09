<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dashboard;
use App\Models\DashboardRecord;
use App\Models\DashboardRecordHistory;
use App\Services\Records\RecordStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecordAdminController extends Controller
{
    public function __construct(private readonly RecordStore $store) {}

    /** Resumen de colecciones declaradas con cantidad de registros y último cambio. */
    public function summary(Dashboard $dashboard): JsonResponse
    {
        $stats = DashboardRecord::query()->where('dashboard_id', $dashboard->id)
            ->selectRaw('collection, count(*) as records, max(updated_at) as last_updated_at')
            ->groupBy('collection')->get()->keyBy('collection');

        $collections = array_map(fn ($c) => [
            'id' => $c['id'],
            'label' => $c['label'] ?? $c['id'],
            'max_records' => $c['maxRecords'] ?? config('dashboards.max_records_per_collection'),
            'records' => (int) ($stats[$c['id']]->records ?? 0),
            'last_updated_at' => isset($stats[$c['id']]) && $stats[$c['id']]->last_updated_at ? date(DATE_ATOM, strtotime($stats[$c['id']]->last_updated_at)) : null,
        ], $dashboard->manifestCollections());

        // Colecciones con datos que ya no están declaradas (quedaron huérfanas tras una actualización).
        $orphans = $stats->keys()->diff(array_column($collections, 'id'))->values()->map(fn ($id) => [
            'id' => $id, 'label' => null, 'max_records' => null, 'records' => (int) $stats[$id]->records, 'last_updated_at' => null,
        ])->all();

        return response()->json(['collections' => $collections, 'orphan_collections' => $orphans]);
    }

    public function records(Dashboard $dashboard, string $collection): JsonResponse
    {
        return response()->json($this->store->list($dashboard, $collection));
    }

    /** Exporta la colección como JSON descargable, con la misma forma que acepta "restaurar". */
    public function export(Dashboard $dashboard, string $collection): StreamedResponse
    {
        $records = DashboardRecord::query()->where('dashboard_id', $dashboard->id)->where('collection', $collection)
            ->orderBy('created_at')->get()->map(fn ($r) => ['id' => $r->record_id, 'data' => $r->data])->values()->all();

        $payload = json_encode([
            'dashboard' => $dashboard->slug, 'collection' => $collection, 'exported_at' => now()->toIso8601String(), 'records' => $records,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $name = "{$dashboard->slug}-{$collection}-".now()->format('Y-m-d').'.json';

        return response()->streamDownload(fn () => print($payload), $name, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public function history(Request $request, Dashboard $dashboard): JsonResponse
    {
        $filters = $request->validate([
            'collection' => ['sometimes', 'nullable', 'string', 'max:60'],
            'record_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'user_id' => ['sometimes', 'nullable', 'uuid'],
            'action' => ['sometimes', 'nullable', Rule::in(['insert', 'update', 'delete'])],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
            'per_page' => ['sometimes', 'integer', 'min:10', 'max:200'],
        ], [
            'user_id.uuid' => 'El filtro de usuario no es válido.',
            'from.date' => 'La fecha "desde" no es válida.',
            'to.date' => 'La fecha "hasta" no es válida.',
        ]);

        $page = DashboardRecordHistory::query()->with('actor')
            ->where('dashboard_id', $dashboard->id)
            ->when($filters['collection'] ?? null, fn ($q, $v) => $q->where('collection', $v))
            ->when($filters['record_id'] ?? null, fn ($q, $v) => $q->where('record_id', $v))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('changed_by', $v))
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('changed_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('changed_at', '<', date('Y-m-d', strtotime($v.' +1 day'))))
            ->orderByDesc('changed_at')
            ->paginate($filters['per_page'] ?? 50);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($h) => [
                'id' => $h->id,
                'collection' => $h->collection,
                'record_id' => $h->record_id,
                'action' => $h->action,
                'version' => $h->version,
                'old_data' => $h->old_data,
                'new_data' => $h->new_data,
                'changed_by' => $h->actor ? ['id' => $h->actor->id, 'name' => $h->actor->name] : null,
                'changed_at' => $h->changed_at?->toIso8601String(),
            ])->all(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }
}
