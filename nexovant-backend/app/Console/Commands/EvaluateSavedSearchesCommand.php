<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\AutomationExecutionLog;
use App\Models\SubscriberSavedSearch;
use App\Services\Discovery\SavedSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class EvaluateSavedSearchesCommand extends Command
{
    protected $signature = 'discovery:evaluate-saved-searches {--cadence=daily : Target alert frequency: daily, weekly, all}';
    protected $description = 'Evaluate persistent subscriber saved searches against recent intelligence and dispatch alerts';

    public function handle(SavedSearchService $savedSearchService): int
    {
        $cadence = (string) $this->option('cadence');
        $lockKey = "command:lock:discovery:evaluate-saved-searches:{$cadence}";
        $lock = Cache::lock($lockKey, 300);

        if (!$lock->get()) {
            $this->warn("Command discovery:evaluate-saved-searches [{$cadence}] is already running. Skipping execution.");
            return self::SUCCESS;
        }

        $log = AutomationExecutionLog::startExecution('discovery:evaluate-saved-searches', 'hourly');
        $this->info("Starting saved-search evaluation for cadence: [{$cadence}]...");

        try {
            $query = SubscriberSavedSearch::where('is_active', true);
            if ($cadence === 'daily') {
                $query->whereIn('alert_frequency', ['daily_digest', 'immediate']);
            } elseif ($cadence === 'weekly') {
                $query->where('alert_frequency', 'weekly_digest');
            }

            $searches = $query->get();
            $this->info("Found {$searches->count()} active saved searches.");

            // Fetch recent articles from the last 24 hours
            $recentArticles = Article::where('published_at', '>=', now()->subHours(24))
                ->with(['source', 'categories'])
                ->get();

            $totalAlerts = 0;
            $failures = 0;
            foreach ($recentArticles as $article) {
                try {
                    $totalAlerts += $savedSearchService->evaluateItemAndAlert($article, 'article');
                } catch (Throwable $e) {
                    $failures++;
                }
            }

            $log->completeSuccess(
                processed: $recentArticles->count(),
                failures: $failures,
                skipped: 0,
                metrics: [
                    'cadence' => $cadence,
                    'active_searches' => $searches->count(),
                    'alerts_dispatched' => $totalAlerts,
                ]
            );

            $this->info("Saved-search evaluation complete. Total alert dispatches generated: {$totalAlerts}.");
            return self::SUCCESS;
        } catch (Throwable $e) {
            $log->failExecution($e->getMessage(), ['cadence' => $cadence]);
            $this->error("Failed to evaluate saved searches: " . $e->getMessage());
            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
