<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\SubscriberSavedSearch;
use App\Services\Discovery\SavedSearchService;
use Illuminate\Console\Command;

class EvaluateSavedSearchesCommand extends Command
{
    protected $signature = 'discovery:evaluate-saved-searches {--cadence=daily : Target alert frequency: daily, weekly, all}';
    protected $description = 'Evaluate persistent subscriber saved searches against recent intelligence and dispatch alerts';

    public function handle(SavedSearchService $savedSearchService): int
    {
        $cadence = (string) $this->option('cadence');
        $this->info("Starting saved-search evaluation for cadence: [{$cadence}]...");

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
        foreach ($recentArticles as $article) {
            $totalAlerts += $savedSearchService->evaluateItemAndAlert($article, 'article');
        }

        $this->info("Saved-search evaluation complete. Total alert dispatches generated: {$totalAlerts}.");
        return self::SUCCESS;
    }
}
