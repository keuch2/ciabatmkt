<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Los usuarios no se borran (sus cambios quedan en el historial): se desactivan.
 */
class UserAdminController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection(User::query()->with(['divisions', 'groups'])->orderBy('name')->get());
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->safe()->except(['division_ids', 'group_ids']);
        $user = User::query()->create($data + ['is_active' => $request->boolean('is_active', true)]);
        $user->syncMemberships((array) $request->input('division_ids', []), (array) $request->input('group_ids', []));

        return (new UserResource($user->load(['divisions', 'groups'])))->response()->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();
        if (array_key_exists('password', $data) && ($data['password'] === null || $data['password'] === '')) {
            unset($data['password']);
        }

        $actor = $request->user();
        if ($actor->id === $user->id) {
            if (($data['is_active'] ?? true) === false) {
                throw ValidationException::withMessages(['is_active' => 'No podés desactivar tu propia cuenta.']);
            }
            if (isset($data['role']) && $data['role'] !== UserRole::SuperAdmin->value) {
                throw ValidationException::withMessages(['role' => 'No podés quitarte el rol de super administrador a vos mismo.']);
            }
        }

        $user->fill(collect($data)->except(['division_ids', 'group_ids'])->all())->save();

        if ($request->has('division_ids') || $request->has('group_ids')) {
            $divisionIds = $request->has('division_ids') ? (array) $request->input('division_ids', []) : $user->divisions()->pluck('divisions.id')->all();
            $groupIds = $request->has('group_ids') ? (array) $request->input('group_ids', []) : $user->groups()->pluck('groups.id')->all();
            $user->syncMemberships($divisionIds, $groupIds);
        }

        return new UserResource($user->refresh()->load(['divisions', 'groups']));
    }
}
