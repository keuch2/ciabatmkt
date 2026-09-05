<?php

namespace App\Http\Resources;

use App\Services\Manifest\HtmlSecurityScanner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dashboard completo para renderizar: html, manifiesto y valores resueltos.
 *
 * @mixin \App\Models\Dashboard
 */
class DashboardResource extends JsonResource
{
    /** @param  array<string, array{value: mixed, source: string, has_override: bool, stale: bool}>  $resolved */
    public function __construct($resource, private readonly array $resolved = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'version' => $this->version,
            'is_published' => $this->is_published,
            'manifest' => $this->manifest,
            'html' => $this->html,
            'params' => $this->resolved,
            'security' => [
                'cdn_allowlist' => config('dashboards.cdn_allowlist'),
                'csp' => app(HtmlSecurityScanner::class)->contentSecurityPolicy(),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
