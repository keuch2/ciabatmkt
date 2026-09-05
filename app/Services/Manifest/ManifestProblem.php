<?php

namespace App\Services\Manifest;

/**
 * Un incumplimiento concreto de una regla del validador de carga, con la ubicación exacta
 * para que el super administrador pueda corregirlo sin adivinar.
 */
final class ManifestProblem
{
    public function __construct(
        public readonly int $rule,
        public readonly string $path,
        public readonly string $message,
    ) {}

    /** @return array{rule: int, path: string, message: string} */
    public function toArray(): array
    {
        return ['rule' => $this->rule, 'path' => $this->path, 'message' => $this->message];
    }
}
