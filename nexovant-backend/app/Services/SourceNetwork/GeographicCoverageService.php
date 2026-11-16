<?php

declare(strict_types=1);

namespace App\Services\SourceNetwork;

use App\Models\Article;
use App\Models\NewsSource;
use Illuminate\Support\Facades\DB;

class GeographicCoverageService
{
    /**
     * Get global geographic coverage breakdown across continents and countries.
     *
     * @return array<string, mixed>
     */
    public function getCoverageBreakdown(): array
    {
        $continentStats = NewsSource::select('continent', DB::raw('count(*) as source_count'), DB::raw('sum(case when is_active = 1 then 1 else 0 end) as active_count'))
            ->groupBy('continent')
            ->get()
            ->map(function ($row) {
                return [
                    'continent' => $row->continent ?: 'Global',
                    'total_sources' => (int) $row->source_count,
                    'active_sources' => (int) $row->active_count,
                ];
            });

        $countryStats = NewsSource::select('country_code', 'continent', DB::raw('count(*) as source_count'), DB::raw('sum(case when is_active = 1 then 1 else 0 end) as active_count'))
            ->groupBy('country_code', 'continent')
            ->orderByDesc('active_count')
            ->get()
            ->map(function ($row) {
                return [
                    'country_code' => $row->country_code ?: 'GLOBAL',
                    'continent' => $row->continent ?: 'Global',
                    'total_sources' => (int) $row->source_count,
                    'active_sources' => (int) $row->active_count,
                    'article_count' => Article::whereHas('source', function ($q) use ($row) {
                        $q->where('country_code', $row->country_code);
                    })->count(),
                ];
            });

        $languageStats = NewsSource::select('language', DB::raw('count(*) as count'))
            ->groupBy('language')
            ->pluck('count', 'language')
            ->toArray();

        return [
            'total_continents' => $continentStats->count(),
            'total_countries_covered' => $countryStats->count(),
            'continents' => $continentStats,
            'countries' => $countryStats,
            'languages' => $languageStats,
            'global_summary' => [
                'total_sources' => NewsSource::count(),
                'active_sources' => NewsSource::where('is_active', true)->count(),
                'total_articles' => Article::count(),
            ],
        ];
    }

    /**
     * Get detailed coverage breakdown for a specific country.
     *
     * @return array<string, mixed>
     */
    public function getCountryCoverage(string $countryCode): array
    {
        $code = strtoupper($countryCode);
        $sources = NewsSource::where('country_code', $code)
            ->withCount('articles')
            ->get();

        $activeSources = $sources->where('is_active', true)->count();
        $totalArticles = $sources->sum('articles_count');

        $byType = $sources->groupBy('source_type')->map->count()->toArray();
        $languages = $sources->pluck('language')->unique()->values()->toArray();

        return [
            'country_code' => $code,
            'total_sources' => $sources->count(),
            'active_sources' => $activeSources,
            'total_articles' => $totalArticles,
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
            ]),
        ];
    }
}
