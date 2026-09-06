<?php

namespace App\Http\Controllers;

use App\Http\Resources\DashboardResource;
use App\Http\Resources\DashboardSummaryResource;
use App\Models\Dashboard;
use App\Services\Params\ParamResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Controllers\Concerns\ResolvesVisibleDashboards;

class DashboardController extends Controller
{
    use ResolvesVisibleDashboards;

    public function __construct(private readonly ParamResolver $resolver) {}

    public function index(): AnonymousResourceCollection
    {
        $dashboards = Dashboard::query()->where('is_published', true)->orderBy('title')->get();

        return DashboardSummaryResource::collection($dashboards);
    }

    public function show(Request $request, Dashboard $dashboard): DashboardResource
    {
        $user = $request->user();
        $this->ensureVisible($dashboard, $user);

        return new DashboardResource($dashboard, $this->resolver->resolve($dashboard, $user->id));
    }
}
