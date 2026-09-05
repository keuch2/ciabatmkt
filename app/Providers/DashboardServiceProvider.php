<?php

namespace App\Providers;

use App\Services\Manifest\HtmlSecurityScanner;
use App\Services\Manifest\ManifestExtractor;
use Illuminate\Support\ServiceProvider;

class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HtmlSecurityScanner::class, fn ($app) => new HtmlSecurityScanner(
            config('dashboards.cdn_allowlist'),
            $app->make(ManifestExtractor::class),
        ));
    }
}
