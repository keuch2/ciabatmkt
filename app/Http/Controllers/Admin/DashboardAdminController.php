<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewDashboardRequest;
use App\Http\Requests\Admin\StoreDashboardRequest;
use App\Http\Requests\Admin\UpdateDashboardRequest;
use App\Http\Resources\DashboardSummaryResource;
use App\Models\Dashboard;
use App\Services\Dashboards\DashboardPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DashboardAdminController extends Controller
{
    public function __construct(private readonly DashboardPublisher $publisher) {}

    public function index(): AnonymousResourceCollection
    {
        return DashboardSummaryResource::collection(
            Dashboard::query()->with('creator')->orderBy('title')->get(),
        );
    }

    /**
     * Corre el validador sin guardar. Si el manifiesto corresponde a un dashboard existente,
     * devuelve además el resumen de cambios para confirmar antes de actualizar.
     */
    public function preview(PreviewDashboardRequest $request): JsonResponse
    {
        $analysis = $this->publisher->analyze($request->string('html'));

        $existing = null;
        $diff = null;
        if ($analysis->manifest !== null && is_string($analysis->manifest['id'] ?? null)) {
            $existing = Dashboard::query()->where('slug', $analysis->manifest['id'])->first();
            if ($existing !== null && $analysis->isValid()) {
                $diff = $this->publisher->diffAgainst($existing, $analysis->manifest);
            }
        }

        $manifest = $analysis->manifest;

        return response()->json([
            'valid' => $analysis->isValid(),
            'problems' => $analysis->problemsArray(),
            'manifest' => $manifest === null ? null : [
                'id' => $manifest['id'] ?? null,
                'title' => $manifest['title'] ?? null,
                'version' => $manifest['version'] ?? null,
                'params' => array_map(fn ($p) => [
                    'id' => $p['id'] ?? null,
                    'label' => $p['label'] ?? null,
                    'type' => $p['type'] ?? null,
                    'default' => $p['default'] ?? null,
                ], is_array($manifest['params'] ?? null) ? $manifest['params'] : []),
            ],
            'existing' => $existing === null ? null : new DashboardSummaryResource($existing),
            'diff' => $diff,
        ]);
    }

    public function store(StoreDashboardRequest $request): JsonResponse
    {
        $analysis = $this->publisher->analyze($request->string('html'));
        $slug = $analysis->manifest['id'] ?? null;

        if (is_string($slug) && ($existing = Dashboard::query()->where('slug', $slug)->first()) !== null) {
            return response()->json([
                'message' => "Ya existe un dashboard con id «{$slug}» ({$existing->title}, versión {$existing->version}). Para reemplazarlo usá la acción de actualizar sobre ese dashboard.",
                'existing_id' => $existing->id,
            ], 409);
        }

        $dashboard = $this->publisher->publish(
            $request->string('html'),
            $request->user(),
            $request->boolean('is_published', true),
        );

        return (new DashboardSummaryResource($dashboard))->response()->setStatusCode(201);
    }

    public function update(UpdateDashboardRequest $request, Dashboard $dashboard): JsonResponse
    {
        $diff = null;

        if ($request->has('html')) {
            ['dashboard' => $dashboard, 'diff' => $diff] = $this->publisher->update($dashboard, $request->string('html'));
        }

        if ($request->has('is_published')) {
            $dashboard->forceFill(['is_published' => $request->boolean('is_published')])->save();
        }

        return response()->json([
            'data' => new DashboardSummaryResource($dashboard->refresh()),
            'diff' => $diff,
        ]);
    }

    public function destroy(Dashboard $dashboard): Response
    {
        // param_values y param_value_history caen en cascada por FK.
        $dashboard->delete();

        return response()->noContent();
    }
}
