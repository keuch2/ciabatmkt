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
        return UserResource::collection(User::query()->orderBy('name')->get());
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::query()->create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return (new UserResource($user))->response()->setStatusCode(201);
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

        $user->fill($data)->save();

        return new UserResource($user->refresh());
    }
}
