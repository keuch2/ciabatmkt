<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDivisionRequest;
use App\Http\Requests\Admin\UpdateDivisionRequest;
use App\Http\Resources\DivisionResource;
use App\Models\Division;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class DivisionAdminController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $divisions = Division::query()
            ->withCount(['users', 'dashboards'])
            ->with(['groups' => fn ($q) => $q->withCount(['users', 'dashboards'])])
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        return DivisionResource::collection($divisions);
    }

    public function store(StoreDivisionRequest $request): JsonResponse
    {
        $division = Division::query()->create($request->validated());

        return (new DivisionResource($this->fresh($division)))->response()->setStatusCode(201);
    }

    public function update(UpdateDivisionRequest $request, Division $division): DivisionResource
    {
        $division->fill($request->validated())->save();

        return new DivisionResource($this->fresh($division));
    }

    /** Se bloquea si la división o alguno de sus grupos tienen dashboards asignados; las membresías caen en cascada. */
    public function destroy(Division $division): Response
    {
        $count = $division->assignedDashboardsCount();
        if ($count > 0) {
            throw ValidationException::withMessages([
                'division' => "La división «{$division->name}» tiene {$count} dashboard(s) asignado(s), directamente o en sus grupos. Reasignalos antes de eliminarla.",
            ]);
        }

        $division->delete();

        return response()->noContent();
    }

    private function fresh(Division $division): Division
    {
        return Division::query()->withCount(['users', 'dashboards'])
            ->with(['groups' => fn ($q) => $q->withCount(['users', 'dashboards'])])
            ->findOrFail($division->id);
    }
}
