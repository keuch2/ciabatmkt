<?php

namespace App\Services\Records;

use Exception;
use Illuminate\Http\JsonResponse;

/** Otro usuario modificó el registro después de que este cliente lo leyó. Responde 409 con la versión actual. */
class RecordConflictException extends Exception
{
    public function __construct(public readonly array $current)
    {
        parent::__construct('Otro usuario modificó este registro mientras lo editabas. Se recargó con la versión más reciente; volvé a aplicar tu cambio.');
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => 'conflict', 'record' => $this->current], 409);
    }
}
