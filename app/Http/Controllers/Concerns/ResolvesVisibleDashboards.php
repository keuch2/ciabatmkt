<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Dashboard;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ResolvesVisibleDashboards
{
    /** Un dashboard sin publicar, o no asignado al usuario, no existe para él. */
    protected function ensureVisible(Dashboard $dashboard, User $user): void
    {
        if (! $dashboard->isVisibleTo($user)) {
            throw new NotFoundHttpException('El dashboard solicitado no existe o no está publicado.');
        }
    }
}
