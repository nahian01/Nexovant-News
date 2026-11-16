<?php

declare(strict_types=1);

namespace App\Services\SourceNetwork;

use App\Models\Article;
use App\Models\NewsSource;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GeographicCoverageService
{
    /**
     * Get global geographic coverage breakdown across continents and countries,
     * with configurable freshness measurement windows (24h, 7d, 30d, 90d, all_time).
     *
     * @return array<string, mixed>
     */
    public function getCoverageBreakdown(string $window = 'all_time'): array
    {
        $cutoff = $this->calculateWindowCutoff($window);

        $continentStats = NewsSource::select('continent', DB::raw('count(*) as source_count'), DB::raw('sum(case when is_active = 1 then 1 else 0 end) as active_count'))
            ->groupBy('continent')
            ->get()
            ->map(function ($row) use ($cutoff) {
                $recentQuery = NewsSource::where('continent', $row->continent)->where('is_active', true);
                if ($cutoff !== null) {
                    $recentQuery->where('last_successful_fetch_at', '>=', $cutoff);
                }
                return [
                    'continent' => $row->continent ?: 'Global',
                    'total_sources' => (int) $row->source_count,
                    'active_sources' => (int) $row->active_count,
                    'sources_with_recent_ingestion' => $recentQuery->count(),
                ];
            });

        $countryStats = NewsSource::select('country_code', 'continent', DB::raw('count(*) as source_count'), DB::raw('sum(case when is_active = 1 then 1 else 0 end) as active_count'))
            ->groupBy('country_code', 'continent')
            ->orderByDesc('active_count')
            ->get()
            ->map(function ($row) use ($cutoff) {
                $recentIngestQuery = NewsSource::where('country_code', $row->country_code)->where('is_active', true);
                if ($cutoff !== null) {
                    $recentIngestQuery->where('last_successful_fetch_at', '>=', $cutoff);
                }

                $articleQuery = Article::whereHas('source', function ($q) use ($row) {
                    $q->where('country_code', $row->country_code);
                });
                if ($cutoff !== null) {
                    $articleQuery->where('published_at', '>=', $cutoff);
                }

                $domainsCount = NewsSource::where('country_code', $row->country_code)
                    ->where('is_active', true)
                    ->distinct('canonical_domain')
                    ->count('canonical_domain');

                return [
                    'country_code' => $row->country_code ?: 'GLOBAL',
                    'continent' => $row->continent ?: 'Global',
                    'total_sources' => (int) $row->source_count,
                    'active_sources' => (int) $row->active_count,
                    'sources_with_recent_ingestion' => $recentIngestQuery->count(),
                    'independent_publishers' => $domainsCount,
                    'article_count' => $articleQuery->count(),
                    'has_sufficient_diversity' => $domainsCount >= 2,
                ];
            });

        $languageStats = NewsSource::select('language', DB::raw('count(*) as count'))
            ->groupBy('language')
            ->pluck('count', 'language')
            ->toArray();

        // 10 Distinct Metrics mandated by Stage 14.1 Requirement 12
        $registeredSources = NewsSource::count();
        $approvedSources = NewsSource::where('editorial_status', 'approved')->count();
        $activeSources = NewsSource::where('is_active', true)->count();

        $recentIngestionQuery = NewsSource::where('is_active', true);
        if ($cutoff !== null) {
            $recentIngestionQuery->where('last_successful_fetch_at', '>=', $cutoff);
        }
        $sourcesWithRecentIngestion = $recentIngestionQuery->count();

        $recentPublicationsQuery = NewsSource::whereHas('articles', function ($q) use ($cutoff) {
            if ($cutoff !== null) {
                $q->where('published_at', '>=', $cutoff);
            }
        });
        $sourcesWithRecentPublications = $recentPublicationsQuery->count();

        $independentReportingOrgs = NewsSource::where('is_active', true)
            ->distinct('canonical_domain')
            ->count('canonical_domain');

        $primarySourcesCount = NewsSource::where('is_active', true)
            ->whereIn('source_type', ['regulatory', 'government', 'academic', 'academic_repository', 'official_disclosure'])
            ->count();

        $countriesRepresentedBySources = NewsSource::whereNotNull('country_code')
            ->distinct('country_code')
            ->count('country_code');

        // Distinguish event location (geographic_scope) from publisher jurisdiction (country_code)
        $articleEventScopeQuery = Article::whereNotNull('geographic_scope');
        if ($cutoff !== null) {
            $articleEventScopeQuery->where('published_at', '>=', $cutoff);
        }
        $countriesRepresentedByEvents = $articleEventScopeQuery->distinct('geographic_scope')->count('geographic_scope');

        $countriesWithSufficientDiversity = $countryStats->filter(fn ($c) => ($c['independent_publishers'] ?? 0) >= 2)->count();

        return [
            'measurement_window' => $window,
            'measurement_window_cutoff' => $cutoff?->toIso8601String(),
            'total_continents' => $continentStats->count(),
            'total_countries_covered' => $countryStats->count(),
            'continents' => $continentStats,
            'countries' => $countryStats,
            'languages' => $languageStats,
            'metrics' => [
                'registered_sources' => $registeredSources,
                'approved_sources' => $approvedSources,
                'active_sources' => $activeSources,
                'sources_with_recent_ingestion' => $sourcesWithRecentIngestion,
                'sources_with_recent_publications' => $sourcesWithRecentPublications,
                'independent_reporting_organizations' => $independentReportingOrgs,
                'primary_sources' => $primarySourcesCount,
                'countries_represented_by_sources' => $countriesRepresentedBySources,
                'countries_represented_by_events' => $countriesRepresentedByEvents,
                'countries_with_sufficient_diversity' => $countriesWithSufficientDiversity,
            ],
            'global_summary' => [
                'total_sources' => $registeredSources,
                'active_sources' => $activeSources,
                'total_articles' => Article::count(),
                'sources_with_recent_ingestion' => $sourcesWithRecentIngestion,
                'countries_represented_by_sources' => $countriesRepresentedBySources,
                'countries_represented_by_events' => $countriesRepresentedByEvents,
            ],
        ];
    }

    /**
     * Get detailed coverage breakdown for a specific country.
     *
     * @return array<string, mixed>
     */
    public function getCountryCoverage(string $countryCode, string $window = 'all_time'): array
    {
        $code = strtoupper($countryCode);
        $cutoff = $this->calculateWindowCutoff($window);

        $sources = NewsSource::where('country_code', $code)
            ->withCount('articles')
            ->get();

        $activeSources = $sources->where('is_active', true)->count();
        $totalArticles = $sources->sum('articles_count');

        $recentIngestedSources = $sources->filter(function (NewsSource $s) use ($cutoff) {
            if (!$s->is_active) return false;
            if ($cutoff === null) return $s->total_items_ingested > 0;
            return $s->last_successful_fetch_at && Carbon::parse($s->last_successful_fetch_at)->greaterThanOrEqualTo($cutoff);
        })->count();

        $byType = $sources->groupBy('source_type')->map->count()->toArray();
        $languages = $sources->pluck('language')->unique()->values()->toArray();

        // Articles originating from this country vs events located in this country
        $articlesCoveringEventsInCountry = Article::where('geographic_scope', $code);
        if ($cutoff !== null) {
            $articlesCoveringEventsInCountry->where('published_at', '>=', $cutoff);
        }
        $eventArticlesCount = $articlesCoveringEventsInCountry->count();

        return [
            'country_code' => $code,
            'measurement_window' => $window,
            'total_sources' => $sources->count(),
            'active_sources' => $activeSources,
            'sources_with_recent_ingestion' => $recentIngestedSources,
            'total_articles' => $totalArticles,
            'event_articles_count' => $eventArticlesCount,
            'source_types' => $byType,
            'supported_languages' => $languages,
            'sources' => $sources->map(fn (NewsSource $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'source_type' => $s->source_type,
                'health_status' => $s->health_status,
                'is_active' => (bool) $s->is_active,
                'editorial_status' => $s->editorial_status,
                'reliability_score' => $s->reliability_profile['overall_score'] ?? 80,
                'articles_count' => $s->articles_count,
                'last_successful_fetch_at' => $s->last_successful_fetch_at,
            ]),
        ];
    }

    /**
     * Compute timestamp cutoff for a named freshness window.
     */
    protected function calculateWindowCutoff(string $window): ?Carbon
    {
        return match (strtolower($window)) {
            '24h', 'last_24h', 'last_24_hours' => now()->subDay(),
            '7d', 'last_7d', 'last_7_days' => now()->subDays(7),
            '30d', 'last_30d', 'last_30_days' => now()->subDays(30),
            '90d', 'last_90d', 'last_90_days' => now()->subDays(90),
            default => null,
        };
    }
}
