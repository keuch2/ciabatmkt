<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGroupRequest;
use App\Http\Requests\Admin\UpdateGroupRequest;
use App\Http\Resources\GroupResource;
use App\Models\Division;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class GroupAdminController extends Controller
{
    public function store(StoreGroupRequest $request, Division $division): JsonResponse
    {
        $group = $division->groups()->create($request->validated());

        return (new GroupResource($group->loadCount(['users', 'dashboards'])))->response()->setStatusCode(201);
    }

    public function update(UpdateGroupRequest $request, Division $division, Group $group): GroupResource
    {
        $group->fill($request->validated())->save();

        return new GroupResource($group->loadCount(['users', 'dashboards']));
    }

    public function destroy(Division $division, Group $group): Response
    {
        $count = $group->dashboards()->count();
        if ($count > 0) {
            throw ValidationException::withMessages([
                'group' => "El grupo «{$group->name}» tiene {$count} dashboard(s) asignado(s). Reasignalos antes de eliminarlo.",
            ]);
        }

        $group->delete();

        return response()->noContent();
    }
}
