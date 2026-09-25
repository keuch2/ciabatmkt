<?php

namespace App\Services\Records;

use App\Models\Dashboard;
use App\Models\DashboardWriteFailure;
use App\Models\User;
use Throwable;

/** Registra escrituras rechazadas y guardados sin cambios, sin interferir con la respuesta. */
class WriteFailureLog
{
    public function record(Dashboard $dashboard, string $collection, ?string $recordId, ?User $user, string $operation, string $code, string $message, ?int $bytes = null): void
    {
        try {
            DashboardWriteFailure::query()->create([
                'dashboard_id' => $dashboard->id,
                'collection' => mb_substr($collection, 0, 60),
                'record_id' => $recordId !== null ? mb_substr($recordId, 0, 100) : null,
                'user_id' => $user?->id,
                'operation' => $operation,
                'code' => $code,
                'message' => mb_substr($message, 0, 500),
                'bytes' => $bytes,
            ]);
        } catch (Throwable) {
            // El registro de fallas nunca debe romper una escritura.
        }
    }
}
