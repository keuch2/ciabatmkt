<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dashboard;
use App\Models\User;
use App\Services\Params\ParamResolver;
use Illuminate\Http\JsonResponse;

/**
 * Vista consolidada de escenarios: qué valor efectivo ve cada usuario para cada parámetro.
 */
class OverviewController extends Controller
{
    public function __construct(private readonly ParamResolver $resolver) {}

    public function __invoke(Dashboard $dashboard): JsonResponse
    {
        $params = array_map(fn ($p) => [
            'id' => $p['id'],
            'label' => $p['label'] ?? $p['id'],
            'type' => $p['type'],
            'unit' => $p['unit'] ?? null,
            'default' => $p['default'],
            'options' => $p['options'] ?? null,
        ], $dashboard->manifestParams());

        $base = $this->resolver->resolve($dashboard, null);

        $users = User::query()->where('is_active', true)->orderBy('name')->get()
            ->map(function (User $user) use ($dashboard) {
                $resolved = $this->resolver->resolve($dashboard, $user->id);

                return [
                    'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role->value],
                    'params' => $resolved,
                    'override_count' => count(array_filter($resolved, fn ($r) => $r['has_override'])),
                ];
            })
            ->sortByDesc('override_count')
            ->values();

        return response()->json([
            'dashboard' => ['id' => $dashboard->id, 'slug' => $dashboard->slug, 'title' => $dashboard->title, 'version' => $dashboard->version],
            'params' => $params,
            'base' => $base,
            'users' => $users,
        ]);
    }
}
