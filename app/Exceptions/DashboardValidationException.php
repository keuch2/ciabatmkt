<?php

namespace App\Exceptions;

use App\Services\Manifest\ManifestProblem;
use Exception;
use Illuminate\Http\JsonResponse;

/**
 * El dashboard no pasó el validador de carga. Se responde 422 con la lista exacta de problemas.
 */
class DashboardValidationException extends Exception
{
    /** @param  list<ManifestProblem>  $problems */
    public function __construct(public readonly array $problems, ?string $message = null)
    {
        $count = count($problems);
        parent::__construct($message ?? ($count === 1
            ? 'El dashboard no pasó la validación: hay 1 problema que corregir.'
            : "El dashboard no pasó la validación: hay {$count} problemas que corregir."));
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'problems' => array_map(fn (ManifestProblem $p) => $p->toArray(), $this->problems),
        ], 422);
    }
}
