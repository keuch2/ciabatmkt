<?php

namespace App\Services\Records;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;

/** Otro usuario modificó el registro después de que este cliente lo leyó. Responde 409 con la versión actual. */
/** Es una situación esperada (dos usuarios editando lo mismo), no un error: no va al log. */
class RecordConflictException extends Exception implements ShouldntReport
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
