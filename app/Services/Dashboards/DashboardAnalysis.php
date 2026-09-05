<?php

namespace App\Services\Dashboards;

use App\Services\Manifest\ManifestProblem;

/** Resultado de analizar un HTML de dashboard: manifiesto extraído y problemas encontrados. */
final class DashboardAnalysis
{
    /** @param  list<ManifestProblem>  $problems */
    public function __construct(
        public readonly ?array $manifest,
        public readonly array $problems,
    ) {}

    public function isValid(): bool
    {
        return $this->problems === [] && $this->manifest !== null;
    }

    /** @return list<array{rule: int, path: string, message: string}> */
    public function problemsArray(): array
    {
        return array_map(fn (ManifestProblem $p) => $p->toArray(), $this->problems);
    }
}
