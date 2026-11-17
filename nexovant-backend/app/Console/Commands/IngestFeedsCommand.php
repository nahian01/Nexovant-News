<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NewsSource;
use App\Services\Ingestion\IntelligencePipelineCoordinator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class IngestFeedsCommand extends Command
{
    protected $signature = 'nexovant:ingest-feeds 
                            {--source= : Specific NewsSource UUID or slug to ingest}
                            {--force : Force ingestion even if fetch interval has not elapsed}
                            {--daemon : Run continuously as a background automated surveillance scanner}
                            {--interval= : Polling check interval in minutes (default 15)}';

    protected $description = 'Ingest RSS/Atom feeds, detect duplicates, cluster stories, and synthesize intelligence articles';

    public function handle(IntelligencePipelineCoordinator $coordinator): int
    {
        $isDaemon = (bool) $this->option('daemon');

        if ($isDaemon) {
            $this->info("Starting Nexovant Continuous Surveillance Scanner Daemon...");
            $this->info("Press Ctrl+C to terminate gracefully.");

            while (true) {
                $this->runIngestionCycle($coordinator);

                $interval = $this->determineInterval();
                $this->info("Sleeping for {$interval} minutes until next intelligence surveillance cycle...");

                for ($i = 0; $i < ($interval * 60); $i += 5) {
                    if (connection_aborted()) {
                        break 2;
                    }
                    sleep(5);
                }
            }

            return self::SUCCESS;
        }

        return $this->runIngestionCycle($coordinator);
    }

    /**
     * Run a single ingestion pass across active news sources.
     */
    protected function runIngestionCycle(IntelligencePipelineCoordinator $coordinator): int
    {
        $this->info("[" . now()->toIso8601String() . "] Initiating Nexovant Intelligence Ingestion Pipeline...");

        // Check if automation is paused in global system settings
        $settingsPath = storage_path('app/settings.json');
        if (File::exists($settingsPath)) {
            $settings = json_decode(File::get($settingsPath), true) ?? [];
            if (!empty($settings['pipeline_automation_paused']) || !empty($settings['is_ingestion_paused'])) {
                $this->warn("Ingestion pipeline is paused in system settings (pipeline_automation_paused / is_ingestion_paused).");
                return self::SUCCESS;
            }
        }

        $sourceQuery = NewsSource::where('is_active', true)->whereNotNull('feed_url');

        if ($sourceIdentifier = $this->option('source')) {
            $sourceQuery->where(function ($q) use ($sourceIdentifier) {
                $q->where('id', $sourceIdentifier)->orWhere('slug', $sourceIdentifier);
            });
        }

        $sources = $sourceQuery->get();

        if ($sources->isEmpty()) {
            $this->warn("No active news sources with valid feeds found for ingestion.");
            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $totalProcessed = 0;
        $totalArticles = 0;

        foreach ($sources as $source) {
            // Check interval unless forced
            if (!$force && $source->last_successful_fetch_at) {
                $intervalMinutes = $source->fetch_interval_minutes ?? 15;
                if ($source->last_successful_fetch_at->diffInMinutes(now()) < $intervalMinutes) {
                    $this->line("Skipping [{$source->name}]: Interval of {$intervalMinutes}m not elapsed.");
                    continue;
                }
            }

            $this->info("Ingesting source: {$source->name} ({$source->feed_url})...");

            try {
                $stats = $coordinator->processSource($source, null, $force);
                $this->info("  ✓ Fetched: {$stats['items_fetched']} items, Created: {$stats['stories_created']} stories, Duplicates: {$stats['duplicates_found']}, Clusters: {$stats['clusters_updated']}, Articles: {$stats['articles_generated']}");
                $totalProcessed++;
                $totalArticles += $stats['articles_generated'];
            } catch (Throwable $e) {
                $this->error("  ✗ Error processing [{$source->name}]: {$e->getMessage()}");
            }
        }

        $this->info("Pipeline cycle completed. {$totalProcessed} sources processed, {$totalArticles} articles synthesized.");
        return self::SUCCESS;
    }

    /**
     * Determine polling interval in minutes.
     */
    protected function determineInterval(): int
    {
        if ($option = $this->option('interval')) {
            return max(1, (int) $option);
        }

        $settingsPath = storage_path('app/settings.json');
        if (File::exists($settingsPath)) {
            $settings = json_decode(File::get($settingsPath), true) ?? [];
            if (!empty($settings['rss_polling_interval_minutes'])) {
                return max(1, (int) $settings['rss_polling_interval_minutes']);
            }
            if (!empty($settings['feed_fetch_interval_minutes'])) {
                return max(1, (int) $settings['feed_fetch_interval_minutes']);
            }
        }

        return 15;
    }
}
