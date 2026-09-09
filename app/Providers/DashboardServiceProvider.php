<?php

namespace App\Providers;

use App\Services\Manifest\HtmlSecurityScanner;
use App\Services\Manifest\ManifestExtractor;
use App\Services\Manifest\ManifestValidator;
use App\Services\Params\ParamValueValidator;
use App\Services\Records\RecordStore;
use Illuminate\Support\ServiceProvider;

class DashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HtmlSecurityScanner::class, fn ($app) => new HtmlSecurityScanner(
            config('dashboards.cdn_allowlist'),
            $app->make(ManifestExtractor::class),
        ));

        $this->app->singleton(ManifestValidator::class, fn ($app) => new ManifestValidator(
            $app->make(ParamValueValidator::class),
            (int) config('dashboards.max_records_per_collection'),
            (int) config('dashboards.max_record_bytes'),
        ));

        $this->app->singleton(RecordStore::class, fn () => new RecordStore(
            (int) config('dashboards.max_records_per_collection'),
            (int) config('dashboards.max_record_bytes'),
        ));
    }
}
