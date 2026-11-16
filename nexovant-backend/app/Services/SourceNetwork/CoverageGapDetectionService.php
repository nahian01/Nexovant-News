<?php

declare(strict_types=1);

namespace App\Services\SourceNetwork;

use App\Models\NewsSource;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CoverageGapDetectionService
{
    /**
     * Detect global coverage gaps across geography, languages, and source types.
     * Explains calculation methodology, supporting evidence, root causes, and recommended source types.
     *
     * @return array<string, mixed>
     */
    public function detectGaps(string $window = 'all_time'): array
    {
        $gaps = [];

        // 1. Regional / Continental Representation Gaps
        $continents = ['Asia', 'Europe', 'North America', 'South America', 'Africa', 'Oceania'];
        $continentSources = NewsSource::select('continent', 'health_status', 'is_active', 'last_successful_fetch_at')->get();

        foreach ($continents as $continent) {
            $matching = $continentSources->where('continent', $continent);
            $totalCount = $matching->count();
            $activeCount = $matching->where('is_active', true)->count();
            $healthyCount = $matching->where('health_status', 'healthy')->count();

            if ($activeCount === 0) {
                $rootCause = $totalCount === 0 ? 'missing_sources' : 'all_sources_inactive';
                $gaps[] = [
                    'gap_type' => 'geographic_void',
                    'severity' => 'critical',
                    'category' => 'Continent',
                    'target' => $continent,
                    'title' => "No active sources in {$continent}",
                    'description' => "Nexovant has zero active intelligence feeds originating from {$continent}.",
                    'calculation_method' => "Active sources count in continent == 0 against target baseline >= 3.",
                    'root_cause' => $rootCause,
                    'evidence' => [
                        'total_registered' => $totalCount,
                        'active_sources' => $activeCount,
                        'healthy_sources' => $healthyCount,
                    ],
                    'recommended_source_types' => ['regulatory', 'rss', 'official_disclosure', 'academic_repository'],
                    'recommended_action' => "Onboard leading regional news agencies, regulatory gazettes, or academic repositories in {$continent}.",
                ];
            } elseif ($activeCount < 3) {
                $rootCause = ($healthyCount === 0 && $activeCount > 0) ? 'ingestion_failure' : 'missing_sources';
                $gaps[] = [
                    'gap_type' => 'under_represented_region',
                    'severity' => 'moderate',
                    'category' => 'Continent',
                    'target' => $continent,
                    'title' => "Low coverage in {$continent} ({$activeCount} sources)",
                    'description' => "Intelligence diversity is low in {$continent}. Consider onboarding additional independent publishers.",
                    'calculation_method' => "Active sources count ({$activeCount}) is below diversity threshold (3 independent feeds).",
                    'root_cause' => $rootCause,
                    'evidence' => [
                        'total_registered' => $totalCount,
                        'active_sources' => $activeCount,
                        'healthy_sources' => $healthyCount,
                    ],
                    'recommended_source_types' => ['rss', 'official_disclosure', 'rest_api'],
                    'recommended_action' => "Expand source network to include independent local publishers and economic regulators.",
                ];
            }
        }

        // 2. High-Priority Country Monopolies or Gaps
        $priorityCountries = [
            'BD' => 'Bangladesh',
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'DE' => 'Germany',
            'FR' => 'France',
            'IN' => 'India',
            'JP' => 'Japan',
            'SG' => 'Singapore',
            'AE' => 'United Arab Emirates',
            'ZA' => 'South Africa',
            'BR' => 'Brazil',
        ];

        $countrySources = NewsSource::select('country_code', 'health_status', 'is_active', 'last_successful_fetch_at')->get();

        foreach ($priorityCountries as $code => $countryName) {
            $matching = $countrySources->where('country_code', $code);
            $totalCount = $matching->count();
            $activeCount = $matching->where('is_active', true)->count();
            $healthyCount = $matching->where('health_status', 'healthy')->count();

            if ($activeCount === 0) {
                $rootCause = $totalCount === 0 ? 'missing_sources' : ($healthyCount === 0 ? 'ingestion_failure' : 'all_sources_inactive');
                $gaps[] = [
                    'gap_type' => 'key_market_missing',
                    'severity' => 'high',
                    'category' => 'Country',
                    'target' => $code,
                    'title' => "Key Market Missing: {$countryName} ({$code})",
                    'description' => "No active sources are indexed for {$countryName}.",
                    'calculation_method' => "Zero active sources registered for tier-1 intelligence priority market {$code}.",
                    'root_cause' => $rootCause,
                    'evidence' => [
                        'total_registered' => $totalCount,
                        'active_sources' => $activeCount,
                        'healthy_sources' => $healthyCount,
                    ],
                    'recommended_source_types' => ['regulatory', 'rss', 'official_disclosure'],
                    'recommended_action' => "Onboard primary national news and central regulatory feeds for {$countryName}.",
                ];
            } elseif ($activeCount === 1) {
                $rootCause = ($healthyCount === 0) ? 'ingestion_failure' : 'single_source_monopoly';
                $gaps[] = [
                    'gap_type' => 'single_source_monopoly',
                    'severity' => 'medium',
                    'category' => 'Country',
                    'target' => $code,
                    'title' => "Single-Source Monopoly Risk: {$countryName} ({$code})",
                    'description' => "Only 1 active source exists for {$countryName}. Risk of editorial bias and lack of independent corroboration.",
                    'calculation_method' => "Active publisher count == 1, failing minimum corroboration threshold (2 distinct publishers).",
                    'root_cause' => $rootCause,
                    'evidence' => [
                        'total_registered' => $totalCount,
                        'active_sources' => $activeCount,
                        'healthy_sources' => $healthyCount,
                    ],
                    'recommended_source_types' => ['rss', 'rest_api'],
                    'recommended_action' => "Onboard at least one additional independent corroborating source.",
                ];
            }
        }

        // 3. Language Representation Gaps
        $essentialLanguages = [
            'en' => 'English',
            'bn' => 'Bengali',
            'fr' => 'French',
            'de' => 'German',
            'es' => 'Spanish',
            'ar' => 'Arabic',
        ];

        $langCounts = NewsSource::where('is_active', true)
            ->select('language', DB::raw('count(*) as count'))
            ->groupBy('language')
            ->pluck('count', 'language')
            ->toArray();

        foreach ($essentialLanguages as $langCode => $langName) {
            $count = $langCounts[$langCode] ?? 0;
            if ($count === 0) {
                $gaps[] = [
                    'gap_type' => 'language_blindspot',
                    'severity' => 'high',
                    'category' => 'Language',
                    'target' => $langCode,
                    'title' => "Language Blindspot: {$langName} ({$langCode})",
                    'description' => "No active native feeds in {$langName}. Multilingual intelligence pipeline cannot ingest local reporting.",
                    'calculation_method' => "Active native feed count == 0 for core intelligence language {$langCode}.",
                    'root_cause' => 'missing_sources',
                    'evidence' => ['language' => $langCode, 'active_feeds' => 0],
                    'recommended_source_types' => ['rss', 'rest_api'],
                    'recommended_action' => "Onboard native {$langName} RSS or REST feeds.",
                ];
            }
        }

        // 4. Primary Source / Regulatory Blindspots
        $primarySources = NewsSource::where('is_active', true)
            ->whereIn('source_type', ['regulatory', 'government', 'academic', 'academic_repository', 'official_disclosure'])
            ->get();
        $primarySourceCount = $primarySources->count();
        $healthyPrimaryCount = $primarySources->where('health_status', 'healthy')->count();

        if ($primarySourceCount < 2) {
            $rootCause = ($primarySourceCount > 0 && $healthyPrimaryCount === 0) ? 'ingestion_failure' : 'missing_sources';
            $gaps[] = [
                'gap_type' => 'regulatory_shortage',
                'severity' => 'high',
                'category' => 'Source Type',
                'target' => 'regulatory',
                'title' => "Low Primary Regulatory Coverage ({$primarySourceCount} sources)",
                'description' => "Insufficient official disclosure and regulatory feeds. Platform relies heavily on secondary news reporting.",
                'calculation_method' => "Primary regulatory source count ({$primarySourceCount}) is below minimum grounding threshold (2 official feeds).",
                'root_cause' => $rootCause,
                'evidence' => [
                    'primary_source_count' => $primarySourceCount,
                    'healthy_primary_count' => $healthyPrimaryCount,
                ],
                'recommended_source_types' => ['regulatory', 'official_disclosure'],
                'recommended_action' => "Add official gazettes, central bank announcement portals, and regulatory disclosures.",
            ];
        }

        return [
            'total_gaps' => count($gaps),
            'critical_gaps' => count(array_filter($gaps, fn ($g) => $g['severity'] === 'critical')),
            'high_gaps' => count(array_filter($gaps, fn ($g) => $g['severity'] === 'high')),
            'moderate_gaps' => count(array_filter($gaps, fn ($g) => $g['severity'] === 'medium' || $g['severity'] === 'moderate')),
            'gaps' => $gaps,
        ];
    }
}
