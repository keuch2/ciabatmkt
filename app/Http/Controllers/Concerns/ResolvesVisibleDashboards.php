<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Dashboard;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ResolvesVisibleDashboards
{
    /** Un dashboard sin publicar no existe para un usuario común. */
    protected function ensureVisible(Dashboard $dashboard, User $user): void
    {
        if (! $dashboard->is_published && ! $user->isSuperAdmin()) {
            throw new NotFoundHttpException('El dashboard solicitado no existe o no está publicado.');
        }
    }
}
