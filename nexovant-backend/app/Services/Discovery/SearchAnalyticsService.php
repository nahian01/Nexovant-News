<?php

declare(strict_types=1);

namespace App\Services\Discovery;

use App\Models\SearchAnalyticsLog;
use App\Models\SearchRelevanceFeedback;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SearchAnalyticsService
{
    /**
     * Log a search execution for privacy-safe telemetry and latency monitoring.
     *
     * @param array<string, mixed> $meta
     */
    public function logSearch(
        string $query,
        int $resultCount,
        int $durationMs,
        string $retrievalMethod = 'keyword',
        string $category = 'all',
        array $filters = [],
        ?User $user = null
    ): SearchAnalyticsLog {
        // Sanitize query to prevent accidental token or credential exposure
        $cleanQuery = substr(trim($query), 0, 255);

        return SearchAnalyticsLog::create([
            'user_id' => $user?->id,
            'organization_id' => $user?->organization_id ?? null,
            'query' => $cleanQuery,
            'search_category' => $category,
            'filters' => !empty($filters) ? $filters : null,
            'result_count' => $resultCount,
            'is_empty' => $resultCount === 0,
            'retrieval_method' => $retrievalMethod,
            'duration_ms' => $durationMs,
            'created_at' => now(),
        ]);
    }

    /**
     * Track user click-through engagement on a specific search result.
     */
    public function recordClick(string $query, string $resultId, string $resultType, ?User $user = null): void
    {
        // Update the most recent log for this query and user/session
        $log = SearchAnalyticsLog::where('query', trim($query))
            ->when($user, fn($q) => $q->where('user_id', $user->id))
            ->latest('created_at')
            ->first();

        if ($log) {
            $log->update([
                'clicked_result_id' => $resultId,
                'clicked_result_type' => $resultType,
            ]);
        }
    }

    /**
     * Record explicit user relevance feedback.
     */
    public function recordFeedback(
        User $user,
        string $query,
        string $resultId,
        string $resultType,
        string $feedbackRating,
        ?string $comments = null
    ): SearchRelevanceFeedback {
        return SearchRelevanceFeedback::create([
            'user_id' => $user->id,
            'query' => trim($query),
            'result_id' => $resultId,
            'result_type' => $resultType,
            'feedback_rating' => $feedbackRating,
            'comments' => $comments,
            'created_at' => now(),
        ]);
    }

    /**
     * Retrieve aggregated admin telemetry on search trends and performance.
     *
     * @return array<string, mixed>
     */
    public function getAdminOverview(): array
    {
        $totalSearches = SearchAnalyticsLog::count();
        $emptySearches = SearchAnalyticsLog::where('is_empty', true)->count();
        $emptyRate = $totalSearches > 0 ? round(($emptySearches / $totalSearches) * 100, 1) : 0;
        $avgDuration = round((float) (SearchAnalyticsLog::avg('duration_ms') ?? 0), 1);

        // Top searched queries
        $topQueries = SearchAnalyticsLog::select('query', DB::raw('count(*) as count'), DB::raw('avg(result_count) as avg_results'))
            ->groupBy('query')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        // Unanswered queries (empty searches)
        $unansweredQueries = SearchAnalyticsLog::select('query', DB::raw('count(*) as frequency'), DB::raw('max(created_at) as last_searched_at'))
            ->where('is_empty', true)
            ->groupBy('query')
            ->orderByDesc('frequency')
            ->limit(10)
            ->get();

        // Retrieval method breakdown
        $methodBreakdown = SearchAnalyticsLog::select('retrieval_method', DB::raw('count(*) as count'))
            ->groupBy('retrieval_method')
            ->get()
            ->pluck('count', 'retrieval_method');

        // Feedback ratings distribution
        $feedbackDistribution = SearchRelevanceFeedback::select('feedback_rating', DB::raw('count(*) as count'))
            ->groupBy('feedback_rating')
            ->get()
            ->pluck('count', 'feedback_rating');

        return [
            'metrics' => [
                'total_searches' => $totalSearches,
                'empty_searches' => $emptySearches,
                'empty_rate_pct' => $emptyRate,
                'avg_latency_ms' => $avgDuration,
                'total_feedback_count' => SearchRelevanceFeedback::count(),
            ],
            'top_queries' => $topQueries,
            'unanswered_queries' => $unansweredQueries,
            'retrieval_methods' => $methodBreakdown,
            'feedback_distribution' => $feedbackDistribution,
        ];
    }
}
