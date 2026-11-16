<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Models\AnomalyObservation;
use App\Models\Article;
use App\Models\IntelligenceSignal;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnomalyDetectionService
{
    public const MIN_SAMPLE_THRESHOLD = 5;

    /**
     * Detect reporting frequency anomalies for a subject (topic or entity).
     *
     * @param string $subjectType topic, entity, technology, geography
     * @param string $subjectName
     * @param string|null $subjectId
     * @param int $baselineDays
     * @param int $observationDays
     * @param IntelligenceSignal|null $signal
     * @param float $sensitivityZScoreConfigurable
     * @return AnomalyObservation
     */
    public function detectReportingFrequencyAnomaly(
        string $subjectType,
        string $subjectName,
        ?string $subjectId = null,
        int $baselineDays = 30,
        int $observationDays = 7,
        ?IntelligenceSignal $signal = null,
        float $sensitivityZScore = 2.0
    ): AnomalyObservation {
        $now = now();
        $obsStart = $now->copy()->subDays($observationDays);
        $baseStart = $obsStart->copy()->subDays($baselineDays);

        // Count historical observations in baseline
        $baselineArticles = Article::where('published_at', '>=', $baseStart)
            ->where('published_at', '<', $obsStart)
            ->where(function ($q) use ($subjectName) {
                $q->where('title', 'like', "%{$subjectName}%")
                  ->orWhere('content_normalized', 'like', "%{$subjectName}%");
            })
            ->count();

        // Count observations in recent observation window
        $observedArticles = Article::where('published_at', '>=', $obsStart)
            ->where(function ($q) use ($subjectName) {
                $q->where('title', 'like', "%{$subjectName}%")
                  ->orWhere('content_normalized', 'like', "%{$subjectName}%");
            })
            ->count();

        $totalSamples = $baselineArticles + $observedArticles;
        $dailyBaselineRate = $baselineDays > 0 ? ($baselineArticles / (float) $baselineDays) : 0.0;
        $expectedObserved = round($dailyBaselineRate * $observationDays, 2);

        // Duplicate suppression check: check if an observation for this subject was recorded in last 12 hours
        $recentDuplicate = AnomalyObservation::where('subject_type', $subjectType)
            ->where('subject_name', $subjectName)
            ->where('indicator_name', 'reporting_frequency')
            ->where('detected_at', '>=', now()->subHours(12))
            ->first();

        $isSuppressed = $recentDuplicate !== null;

        // Determine Media Coverage Surge vs Real-World Underlying Event Cluster
        $coverageVsRealityType = 'media_coverage_surge';
        if ($signal) {
            $isCorroborated = in_array($signal->verification_status, ['corroborated', 'verified', 'reported_reliable'], true);
            if ($signal->independent_sources_count >= 3 && $isCorroborated) {
                $coverageVsRealityType = 'underlying_event_cluster';
            } elseif ($signal->independent_sources_count === 1 || $signal->verification_status === 'unverified') {
                $coverageVsRealityType = 'media_coverage_surge';
            }
        }

        // Guardrail: Minimum sample size requirement to avoid false precision on trivial fluctuations
        if ($totalSamples < self::MIN_SAMPLE_THRESHOLD) {
            return AnomalyObservation::create([
                'signal_id' => $signal?->id,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'subject_name' => $subjectName,
                'indicator_name' => 'reporting_frequency',
                'baseline_period_days' => $baselineDays,
                'observation_period_days' => $observationDays,
                'observed_value' => (float) $observedArticles,
                'expected_value' => (float) $expectedObserved,
                'deviation_score' => 0.0,
                'coverage_vs_reality_type' => $coverageVsRealityType,
                'is_suppressed' => $isSuppressed,
                'detection_method' => 'moving_average_zscore',
                'data_sufficiency' => 'insufficient',
                'sample_count' => $totalSamples,
                'min_sample_threshold' => self::MIN_SAMPLE_THRESHOLD,
                'limitations' => "Insufficient sample size ({$totalSamples} < " . self::MIN_SAMPLE_THRESHOLD . "). Statistical baseline cannot be reliably established without minimum reporting volume.",
                'detected_at' => now(),
            ]);
        }

        // Zero-Variance Baseline Protection:
        // Use a safe floor max($stdDev, 0.5) so that zero-variance baseline does not cause division by zero or infinite z-scores.
        $stdDev = max(0.5, sqrt(max(0.25, (float) $expectedObserved)));
        $deviationScore = round(($observedArticles - $expectedObserved) / $stdDev, 2);

        $sufficiency = $totalSamples >= 20 ? 'robust' : 'sufficient';

        $limitations = match (true) {
            $deviationScore > 3.0 => "Statistically significant surge (>3 sigma) over rolling baseline. Verified as [{$coverageVsRealityType}].",
            $deviationScore >= $sensitivityZScore => "Elevated volume exceeding configurable threshold (z >= {$sensitivityZScore}). Classified as [{$coverageVsRealityType}].",
            default => 'Normal variation within expected statistical bounds.'
        };

        return AnomalyObservation::create([
            'signal_id' => $signal?->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_name' => $subjectName,
            'indicator_name' => 'reporting_frequency',
            'baseline_period_days' => $baselineDays,
            'observation_period_days' => $observationDays,
            'observed_value' => (float) $observedArticles,
            'expected_value' => (float) $expectedObserved,
            'deviation_score' => (float) $deviationScore,
            'coverage_vs_reality_type' => $coverageVsRealityType,
            'is_suppressed' => $isSuppressed,
            'detection_method' => 'moving_average_zscore',
            'data_sufficiency' => $sufficiency,
            'sample_count' => $totalSamples,
            'min_sample_threshold' => self::MIN_SAMPLE_THRESHOLD,
            'limitations' => $limitations,
            'detected_at' => now(),
        ]);
    }

    /**
     * Detect sudden contradiction surge across claims.
     */
    public function detectContradictionSpike(
        string $topicName,
        int $contradictionCount,
        int $totalClaimsCount,
        ?IntelligenceSignal $signal = null
    ): AnomalyObservation {
        $ratio = $totalClaimsCount > 0 ? round(($contradictionCount / (float) $totalClaimsCount) * 100.0, 2) : 0.0;
        $expectedRatio = 5.0; // Baseline expectation: 5% claim dispute rate

        $isInsufficient = $totalClaimsCount < self::MIN_SAMPLE_THRESHOLD;
        $deviation = $isInsufficient ? 0.0 : round($ratio - $expectedRatio, 2);

        $recentDuplicate = AnomalyObservation::where('subject_type', 'topic')
            ->where('subject_name', $topicName)
            ->where('indicator_name', 'contradiction_frequency')
            ->where('detected_at', '>=', now()->subHours(12))
            ->first();

        return AnomalyObservation::create([
            'signal_id' => $signal?->id,
            'subject_type' => 'topic',
            'subject_name' => $topicName,
            'indicator_name' => 'contradiction_frequency',
            'baseline_period_days' => 30,
            'observation_period_days' => 7,
            'observed_value' => (float) $ratio,
            'expected_value' => (float) $expectedRatio,
            'deviation_score' => (float) $deviation,
            'coverage_vs_reality_type' => 'contested_reporting_anomaly',
            'is_suppressed' => $recentDuplicate !== null,
            'detection_method' => 'ratio_surge',
            'data_sufficiency' => $isInsufficient ? 'insufficient' : 'sufficient',
            'sample_count' => $totalClaimsCount,
            'min_sample_threshold' => self::MIN_SAMPLE_THRESHOLD,
            'limitations' => $isInsufficient
                ? "Fewer than " . self::MIN_SAMPLE_THRESHOLD . " claims evaluated. Claim divergence rate not statistically conclusive."
                : ($ratio > 25.0 ? 'Elevated contradictory assertions across monitored publications.' : 'Normal reporting convergence.'),
            'detected_at' => now(),
        ]);
    }
}
