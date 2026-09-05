<?php

namespace App\Services\Dashboards;

use App\Exceptions\DashboardValidationException;
use App\Models\Dashboard;
use App\Models\User;
use App\Services\Manifest\HtmlSecurityScanner;
use App\Services\Manifest\ManifestDiff;
use App\Services\Manifest\ManifestExtractor;
use App\Services\Manifest\ManifestProblem;
use App\Services\Manifest\ManifestValidator;

/**
 * Orquesta las diez reglas del validador de carga y persiste el dashboard.
 */
class DashboardPublisher
{
    public function __construct(
        private readonly ManifestExtractor $extractor,
        private readonly ManifestValidator $validator,
        private readonly HtmlSecurityScanner $scanner,
        private readonly ManifestDiff $diff,
    ) {}

    /** Corre las diez reglas sin persistir nada. */
    public function analyze(string $html): DashboardAnalysis
    {
        $extracted = $this->extractor->extract($html);
        if ($extracted['manifest'] === null) {
            return new DashboardAnalysis(null, $extracted['problems']);
        }

        $problems = [
            ...$this->validator->validate($extracted['manifest']),
            ...$this->scanner->scan($html),
        ];

        return new DashboardAnalysis($extracted['manifest'], $problems);
    }

    /**
     * Publica un dashboard nuevo. Lanza DashboardValidationException si no pasa las reglas.
     */
    public function publish(string $html, User $author, bool $isPublished = true): Dashboard
    {
        $analysis = $this->analyzeOrFail($html);
        $manifest = $analysis->manifest;

        return Dashboard::query()->create([
            'slug' => $manifest['id'],
            'title' => $manifest['title'],
            'version' => $manifest['version'],
            'html' => $html,
            'manifest' => $manifest,
            'is_published' => $isPublished,
            'created_by' => $author->id,
        ]);
    }

    /**
     * Reemplaza el HTML de un dashboard existente. El id del manifiesto debe coincidir con el slug.
     *
     * @return array{dashboard: Dashboard, diff: array}
     */
    public function update(Dashboard $dashboard, string $html): array
    {
        $analysis = $this->analyzeOrFail($html);
        $manifest = $analysis->manifest;

        if ($manifest['id'] !== $dashboard->slug) {
            throw new DashboardValidationException([
                new ManifestProblem(3, 'id', "El manifiesto tiene id «{$manifest['id']}» pero este dashboard es «{$dashboard->slug}». Para reemplazarlo el id debe coincidir; si es otro dashboard, publicalo como nuevo."),
            ]);
        }

        $diff = $this->diff->compare($dashboard->manifest, $manifest);

        $dashboard->fill([
            'title' => $manifest['title'],
            'version' => $manifest['version'],
            'html' => $html,
            'manifest' => $manifest,
        ])->save();

        return ['dashboard' => $dashboard->refresh(), 'diff' => $diff];
    }

    public function diffAgainst(Dashboard $dashboard, array $newManifest): array
    {
        return $this->diff->compare($dashboard->manifest, $newManifest);
    }

    private function analyzeOrFail(string $html): DashboardAnalysis
    {
        $analysis = $this->analyze($html);
        if (! $analysis->isValid()) {
            throw new DashboardValidationException($analysis->problems);
        }

        return $analysis;
    }
}
