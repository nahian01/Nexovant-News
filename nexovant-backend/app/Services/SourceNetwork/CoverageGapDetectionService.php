<?php

declare(strict_types=1);

namespace App\Services\SourceNetwork;

use App\Models\NewsSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CoverageGapDetectionService
{
    /**
     * Detect global coverage gaps across geography, languages, and source types.
     *
     * @return array<string, mixed>
     */
    public function detectGaps(): array
    {
        $gaps = [];

        // 1. Regional / Continental Representation Gaps
        $continents = ['Asia', 'Europe', 'North America', 'South America', 'Africa', 'Oceania'];
        $continentCounts = NewsSource::where('is_active', true)
            ->select('continent', DB::raw('count(*) as count'))
            ->groupBy('continent')
            ->pluck('count', 'continent')
            ->toArray();

        foreach ($continents as $continent) {
            $count = $continentCounts[$continent] ?? 0;
            if ($count === 0) {
                $gaps[] = [
                    'gap_type' => 'geographic_void',
                    'severity' => 'critical',
                    'category' => 'Continent',
                    'target' => $continent,
                    'title' => "No active sources in {$continent}",
                    'description' => "Nexovant has zero active intelligence feeds originating from {$continent}.",
                    'recommended_action' => "Onboard leading regional news agencies, regulatory gazettes, or academic repositories in {$continent}.",
                ];
            } elseif ($count < 3) {
                $gaps[] = [
                    'gap_type' => 'under_represented_region',
                    'severity' => 'moderate',
                    'category' => 'Continent',
                    'target' => $continent,
                    'title' => "Low coverage in {$continent} ({$count} sources)",
                    'description' => "Intelligence diversity is low in {$continent}. Consider onboarding additional independent publishers.",
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

        $countryCounts = NewsSource::where('is_active', true)
            ->select('country_code', DB::raw('count(*) as count'))
            ->groupBy('country_code')
            ->pluck('count', 'country_code')
            ->toArray();

        foreach ($priorityCountries as $code => $countryName) {
            $count = $countryCounts[$code] ?? 0;
            if ($count === 0) {
                $gaps[] = [
                    'gap_type' => 'key_market_missing',
                    'severity' => 'high',
                    'category' => 'Country',
                    'target' => $code,
                    'title' => "Key Market Missing: {$countryName} ({$code})",
                    'description' => "No active sources are indexed for {$countryName}.",
                    'recommended_action' => "Onboard primary national news and central regulatory feeds for {$countryName}.",
                ];
            } elseif ($count === 1) {
                $gaps[] = [
                    'gap_type' => 'single_source_monopoly',
                    'severity' => 'medium',
                    'category' => 'Country',
                    'target' => $code,
                    'title' => "Single-Source Monopoly Risk: {$countryName} ({$code})",
                    'description' => "Only 1 active source exists for {$countryName}. Risk of editorial bias and lack of independent corroboration.",
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
                    'recommended_action' => "Onboard native {$langName} RSS or REST feeds.",
                ];
            }
        }

        // 4. Primary Source / Regulatory Blindspots
        $primarySourceCount = NewsSource::where('is_active', true)
            ->whereIn('source_type', ['regulatory', 'government', 'academic', 'academic_repository', 'official_disclosure'])
            ->count();

        if ($primarySourceCount < 2) {
            $gaps[] = [
                'gap_type' => 'regulatory_shortage',
                'severity' => 'high',
                'category' => 'Source Type',
                'target' => 'regulatory',
                'title' => "Low Primary Regulatory Coverage ({$primarySourceCount} sources)",
                'description' => "Insufficient official disclosure and regulatory feeds. Platform relies heavily on secondary news reporting.",
                'recommended_action' => "Add official gazettes, central bank announcement portals, and regulatory disclosures.",
            ];
        }

        return [
            'total_gaps' => count($gaps),
            'critical_count' => count(array_filter($gaps, fn ($g) => $g['severity'] === 'critical')),
            'high_count' => count(array_filter($gaps, fn ($g) => $g['severity'] === 'high')),
            'moderate_count' => count(array_filter($gaps, fn ($g) => in_array($g['severity'], ['medium', 'moderate']))),
            'gaps' => $gaps,
        ];
    }
}
