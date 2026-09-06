<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDashboards;
use App\Http\Resources\HistoryEntryResource;
use App\Models\Dashboard;
use App\Models\ParamValueHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    use ResolvesVisibleDashboards;

    /** GET /api/dashboards/{id}/history?param_id=&page=  historial propio del usuario. */
    public function index(Request $request, Dashboard $dashboard): JsonResponse
    {
        $user = $request->user();
        $this->ensureVisible($dashboard, $user);

        $page = ParamValueHistory::query()
            ->with(['actor', 'user'])
            ->where('dashboard_id', $dashboard->id)
            ->where('user_id', $user->id)
            ->when($request->filled('param_id'), fn ($q) => $q->where('param_id', $request->string('param_id')))
            ->orderByDesc('changed_at')
            ->paginate(50);

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
