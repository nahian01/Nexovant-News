<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\NewsSource;
use App\Services\Ingestion\IntelligencePipelineCoordinator;
use Illuminate\Console\Command;
use Throwable;

class IngestFeedsCommand extends Command
{
    protected $signature = 'nexovant:ingest-feeds 
                            {--source= : Specific NewsSource UUID or slug to ingest}
                            {--force : Force ingestion even if fetch interval has not elapsed}';

    protected $description = 'Ingest RSS/Atom feeds, detect duplicates, cluster stories, and synthesize intelligence articles';

    public function handle(IntelligencePipelineCoordinator $coordinator): int
    {
        $this->info("Starting Nexovant Intelligence Ingestion Pipeline...");

        $sourceQuery = NewsSource::where('is_active', true);

        if ($sourceIdentifier = $this->option('source')) {
            $sourceQuery->where(function ($q) use ($sourceIdentifier) {
                $q->where('id', $sourceIdentifier)->orWhere('slug', $sourceIdentifier);
            });
        }

        $sources = $sourceQuery->get();

        if ($sources->isEmpty()) {
            $this->warn("No active news sources found for ingestion.");
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

            $this->info("Ingesting source: {$source->name} ({$source->url})...");

            try {
                $stats = $coordinator->processSource($source);
                $this->info("  ✓ Fetched: {$stats['items_fetched']} items, Created: {$stats['stories_created']} stories, Duplicates: {$stats['duplicates_found']}, Clusters: {$stats['clusters_updated']}, Articles: {$stats['articles_generated']}");
                $totalProcessed++;
                $totalArticles += $stats['articles_generated'];
            } catch (Throwable $e) {
                $this->error("  ✗ Error processing [{$source->name}]: {$e->getMessage()}");
            }
        }

        $this->info("Pipeline ingestion completed. {$totalProcessed} sources processed, {$totalArticles} articles synthesized.");
        return self::SUCCESS;
    }
}
