<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesVisibleDashboards;
use App\Http\Requests\UpdateParamValueRequest;
use App\Models\Dashboard;
use App\Models\User;
use App\Services\Params\ParamResolver;
use App\Services\Params\ParamWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ParamValueController extends Controller
{
    use ResolvesVisibleDashboards;

    public function __construct(
        private readonly ParamWriter $writer,
        private readonly ParamResolver $resolver,
    ) {}

    /** PUT /api/dashboards/{id}/params/{paramId}  { value, scope } */
    public function update(UpdateParamValueRequest $request, Dashboard $dashboard, string $paramId): JsonResponse
    {
        $user = $request->user();
        $this->ensureVisible($dashboard, $user);
        $targetUserId = $this->targetUserId($request->scope(), $user);

        $this->writer->write($dashboard, $paramId, $request->input('value'), $targetUserId, $user);

        return response()->json(['data' => $this->resolver->resolveOne($dashboard, $targetUserId, $paramId)]);
    }

    /** DELETE /api/dashboards/{id}/params/{paramId}?scope=  borra el override; vuelve al base. */
    public function destroy(Request $request, Dashboard $dashboard, string $paramId): JsonResponse
    {
        $user = $request->user();
        $this->ensureVisible($dashboard, $user);
        $targetUserId = $this->targetUserId($this->scopeFromQuery($request), $user);

        $this->writer->remove($dashboard, $paramId, $targetUserId, $user);

        $resolved = $this->resolver->resolveOne($dashboard, $targetUserId, $paramId);

        // Un parámetro huérfano (ya no está en el manifiesto) se puede borrar pero no resolver.
        return response()->json(['data' => $resolved ?? ['param_id' => $paramId, 'value' => null, 'source' => null, 'has_override' => false, 'stale' => false]]);
    }

    /** DELETE /api/dashboards/{id}/params?scope=  borra todos los overrides del usuario. */
    public function destroyAll(Request $request, Dashboard $dashboard): JsonResponse
    {
        $user = $request->user();
        $this->ensureVisible($dashboard, $user);
        $targetUserId = $this->targetUserId($this->scopeFromQuery($request), $user);

        $removed = $this->writer->removeAll($dashboard, $targetUserId, $user);

        return response()->json([
            'removed' => $removed,
            'data' => $this->resolver->resolve($dashboard, $targetUserId),
        ]);
    }

    /**
     * El user_id nunca viaja en el cuerpo: sale de la sesión. scope=base escribe el valor
     * para todos y sólo lo puede hacer un super administrador.
     */
    private function targetUserId(string $scope, User $user): ?string
    {
        if ($scope === 'base') {
            if (! $user->isSuperAdmin()) {
                throw new AccessDeniedHttpException('Sólo un super administrador puede definir valores base. Usá scope "user" para guardar tu propio valor.');
            }

            return null;
        }

        return $user->id;
    }

    private function scopeFromQuery(Request $request): string
    {
        $scope = $request->query('scope', 'user');
        if (! in_array($scope, ['user', 'base'], true)) {
            throw ValidationException::withMessages(['scope' => 'El parámetro "scope" debe ser "user" o "base".']);
        }

        return $scope;
    }
}
