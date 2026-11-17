<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Resources\Json\JsonResource;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Remove 'data' wrapper from single resource returns for clean API response structures
        JsonResource::withoutWrapping();

        // Automatic opportunistic background surveillance trigger on web requests
        if (!$this->app->runningInConsole()) {
            $this->app->terminating(function () {
                try {
                    app(\App\Services\Ingestion\AutoIngestSchedulerService::class)->runDueIngestionCycles();
                } catch (\Throwable) {
                    // Fail silently in terminating callback to preserve user response integrity
                }
            });
        }
    }
}
