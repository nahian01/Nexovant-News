<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

use App\Models\Article;
use App\Models\NewsSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class AutoIngestSchedulerService
{
    public function __construct(
        protected IntelligencePipelineCoordinator $coordinator
    ) {}

    /**
     * Check if any active sources are due for ingestion and execute cycle if due.
     * Enforces the hourly cadence targeting at least 20 new articles per hour across internet sources.
     * Guaranteed atomic with lock protection so multiple workers/requests never collide.
     *
     * @return array{sources_checked: int, sources_ingested: int, articles_generated: int, articles_last_hour: int, hourly_target: int}
     */
    public function runDueIngestionCycles(bool $force = false): array
    {
        $settingsPath = storage_path('app/settings.json');
        $hourlyTarget = 20;

        if (File::exists($settingsPath)) {
            $settings = json_decode(File::get($settingsPath), true) ?? [];
            if (!empty($settings['pipeline_automation_paused']) || !empty($settings['is_ingestion_paused'])) {
                return [
                    'sources_checked' => 0,
                    'sources_ingested' => 0,
                    'articles_generated' => 0,
                    'articles_last_hour' => Article::where('created_at', '>=', now()->subHour())->count(),
                    'hourly_target' => (int) ($settings['hourly_article_target'] ?? 20),
                ];
            }
            $hourlyTarget = (int) ($settings['hourly_article_target'] ?? 20);
        }

        $lock = Cache::lock('nexovant_ingestion_pipeline_lock', 240);
        if (!$lock->get()) {
            return [
                'sources_checked' => 0,
                'sources_ingested' => 0,
                'articles_generated' => 0,
                'articles_last_hour' => Article::where('created_at', '>=', now()->subHour())->count(),
                'hourly_target' => $hourlyTarget,
            ];
        }

        $stats = [
            'sources_checked' => 0,
            'sources_ingested' => 0,
            'articles_generated' => 0,
            'articles_last_hour' => 0,
            'hourly_target' => $hourlyTarget,
        ];

        try {
            $articlesLastHour = Article::where('created_at', '>=', now()->subHour())->count();
            $needsQuotaCatchup = $articlesLastHour < $hourlyTarget;

            $sources = NewsSource::where('is_active', true)->whereNotNull('feed_url')->get();
            $stats['sources_checked'] = $sources->count();

            foreach ($sources as $source) {
                if (!$force && $source->last_successful_fetch_at) {
                    // If we are below the hourly quota (20+ articles/hr), shorten interval threshold to 5 mins
                    $intervalMinutes = $needsQuotaCatchup
                        ? 5
                        : max(1, (int) ($source->fetch_interval_minutes ?? 15));

                    if ($source->last_successful_fetch_at->diffInMinutes(now()) < $intervalMinutes) {
                        continue;
                    }
                }

                try {
                    $res = $this->coordinator->processSource($source, null, $force || $needsQuotaCatchup);
                    $stats['sources_ingested']++;
                    $stats['articles_generated'] += $res['articles_generated'];
                } catch (Throwable $e) {
                    Log::warning("AutoIngestSchedulerService error on [{$source->name}]: " . $e->getMessage());
                }
            }

            $currentHourlyCount = Article::where('created_at', '>=', now()->subHour())->count();
            $stats['articles_last_hour'] = $currentHourlyCount;

            // Update heartbeat & hourly metrics in settings.json
            if (File::exists($settingsPath)) {
                $settings = json_decode(File::get($settingsPath), true) ?? [];
                $settings['hourly_article_target'] = $hourlyTarget;
                $settings['articles_ingested_last_hour'] = $currentHourlyCount;
                $settings['last_automated_scan_at'] = now()->toIso8601String();
                File::put($settingsPath, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }
        } finally {
            $lock->release();
        }

        return $stats;
    }
}
