<?php

declare(strict_types=1);

namespace App\Services\SourceNetwork;

use App\Models\Article;
use App\Models\NewsSource;
use Illuminate\Support\Facades\DB;

class SourceReliabilityService
{
    /**
     * Compute and update the source reliability profile based on real publication data.
     *
     * @return array<string, mixed>
     */
    public function calculateReliabilityProfile(NewsSource $source): array
    {
        $existing = is_array($source->reliability_profile) ? $source->reliability_profile : [];
        $articleCount = Article::where('source_id', $source->id)->count();

        // 1. Primary source ratio
        $isPrimaryType = in_array($source->source_type, ['regulatory', 'government', 'academic', 'academic_repository', 'official_disclosure'], true);
        $primarySourceRatio = $isPrimaryType ? 0.95 : ($existing['primary_source_ratio'] ?? 0.65);

        // 2. Transparency Score (0-100)
        $transparency = 40;
        if (!empty($source->publisher_name)) $transparency += 15;
        if (!empty($source->canonical_domain)) $transparency += 15;
        if (!empty($source->terms_of_use_url)) $transparency += 15;
        if (!empty($source->attribution_requirements)) $transparency += 15;
        $transparencyScore = min(100, $transparency);

        // 3. Factual Accuracy Score (0-100)
        $correctionsCount = (int) ($existing['corrections_count'] ?? 0);
        $baseAccuracy = $isPrimaryType ? 98 : 88;
        $factualAccuracy = max(40, $baseAccuracy - ($correctionsCount * 4));

        // 4. Corroboration Rate (0-100)
        $corroborationRate = (float) ($existing['corroboration_rate'] ?? ($isPrimaryType ? 92.0 : 82.0));

        // 5. Overall Score Calculation
        $overallScore = round(
            (0.35 * $factualAccuracy) +
            (0.25 * $corroborationRate) +
            (0.20 * ($primarySourceRatio * 100)) +
            (0.20 * $transparencyScore),
            1
        );

        // 6. Tier Determination
        $tier = 'Tier 2';
        if ($isPrimaryType || $overallScore >= 90) {
            $tier = 'Tier 1';
        } elseif ($overallScore >= 75) {
            $tier = 'Tier 2';
        } elseif ($overallScore >= 60) {
            $tier = 'Tier 3';
        } else {
            $tier = 'Tier 4';
        }

        $profile = [
            'tier' => $tier,
            'overall_score' => $overallScore,
            'factual_accuracy_score' => $factualAccuracy,
            'transparency_score' => $transparencyScore,
            'corroboration_rate' => $corroborationRate,
            'primary_source_ratio' => $primarySourceRatio,
            'corrections_count' => $correctionsCount,
            'corrections_history' => $existing['corrections_history'] ?? [],
            'articles_evaluated' => $articleCount,
            'last_evaluated_at' => now()->toIso8601String(),
            'notes' => $existing['notes'] ?? "Automated reliability assessment based on source profile and attribution metadata.",
        ];

        return $profile;
    }

    /**
     * Update source with calculated reliability profile.
     */
    public function updateSourceReliability(NewsSource $source): NewsSource
    {
        $profile = $this->calculateReliabilityProfile($source);
        $source->update([
            'reliability_profile' => $profile,
        ]);

        return $source->fresh();
    }

    /**
     * Record a factual correction against a source.
     */
    public function recordCorrection(NewsSource $source, string $details): NewsSource
    {
        $profile = is_array($source->reliability_profile) ? $source->reliability_profile : $this->calculateReliabilityProfile($source);
        $corrections = (int) ($profile['corrections_count'] ?? 0) + 1;
        $profile['corrections_count'] = $corrections;

        $history = $profile['corrections_history'] ?? [];
        $history[] = [
            'date' => now()->toIso8601String(),
            'details' => $details,
        ];
        $profile['corrections_history'] = array_slice($history, -10);

        $source->reliability_profile = $profile;
        $source->save();

        return $this->updateSourceReliability($source);
    }
}
