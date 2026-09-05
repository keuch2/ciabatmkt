<?php

namespace App\Http\Controllers;

use App\Http\Resources\DashboardResource;
use App\Http\Resources\DashboardSummaryResource;
use App\Models\Dashboard;
use App\Services\Params\ParamResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DashboardController extends Controller
{
    public function __construct(private readonly ParamResolver $resolver) {}

    public function index(): AnonymousResourceCollection
    {
        $dashboards = Dashboard::query()->where('is_published', true)->orderBy('title')->get();

        return DashboardSummaryResource::collection($dashboards);
    }

    public function show(Request $request, Dashboard $dashboard): DashboardResource
    {
        $user = $request->user();

        // Un dashboard sin publicar no existe para un usuario común.
        if (! $dashboard->is_published && ! $user->isSuperAdmin()) {
            throw new NotFoundHttpException('El dashboard solicitado no existe o no está publicado.');
        }

        return new DashboardResource($dashboard, $this->resolver->resolve($dashboard, $user->id));
    }
}
