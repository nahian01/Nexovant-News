<?php

declare(strict_types=1);

namespace App\Services\Research;

use App\Models\ClaimEvidence;
use App\Models\ExtractedFact;
use App\Models\FactVerificationHistory;
use App\Models\NewsSource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AdvancedFactVerificationService
{
    /**
     * Attach a new piece of evidence to a factual claim, evaluating its stance, syndication, and updating the claim status.
     *
     * @param array<string, mixed> $options
     */
    public function attachEvidence(
        ExtractedFact $fact,
        NewsSource $source,
        string $evidenceText,
        string $stance = 'supports', // supports, contradicts, neutral, refines
        ?string $sourceUrl = null,
        array $options = []
    ): ClaimEvidence {
        return DB::transaction(function () use ($fact, $source, $evidenceText, $stance, $sourceUrl, $options) {
            // 1. Detect Syndication / Press Release Wire Duplication
            // If the evidence text is >88% identical to original evidence excerpt or previously recorded evidence, flag as syndicated reprint
            $isSyndicated = false;

            if (!empty($fact->evidence_excerpt) && $this->calculateTextSimilarity($evidenceText, $fact->evidence_excerpt) >= 0.88) {
                $isSyndicated = true;
            }

            if (!$isSyndicated) {
                $existingEvidences = $fact->evidences()->get();
                foreach ($existingEvidences as $existing) {
                    if ($this->calculateTextSimilarity($evidenceText, $existing->evidence_text) >= 0.88) {
                        $isSyndicated = true;
                        break;
                    }
                }
            }

            // Calculate source reliability based on trust tier & score
            $tierWeight = match ($source->trust_tier) {
                'established_primary', 'official_announcement' => 1.0,
                'reputable_secondary' => 0.85,
                'specialized_industry' => 0.80,
                default => 0.60,
            };
            $reliabilityScore = min(1.0, max(0.1, ($source->credibility_score / 100) * $tierWeight));

            $evidence = ClaimEvidence::create([
                'fact_id' => $fact->id,
                'source_id' => $source->id,
                'story_id' => $options['story_id'] ?? null,
                'stance' => $stance,
                'evidence_text' => trim($evidenceText),
                'source_url' => $sourceUrl,
                'published_at' => $options['published_at'] ?? now(),
                'is_syndicated_reprint' => $isSyndicated,
                'reliability_score' => $reliabilityScore,
                'reviewer_decision' => 'pending',
            ]);

            // 2. Re-evaluate Claim Status and Verification Dimensions
            $this->recomputeFactStatus($fact);

            return $evidence;
        });
    }

    /**
     * Recompute verification status and 10 dimensions for a claim based on all attached evidence.
     */
    public function recomputeFactStatus(ExtractedFact $fact): ExtractedFact
    {
        $evidences = $fact->evidences()->with('source')->get();
        if ($evidences->isEmpty()) {
            return $fact;
        }

        $supporting = $evidences->where('stance', 'supports');
        $contradicting = $evidences->where('stance', 'contradicts');

        // Check independent non-syndicated sources supporting (including the original source of the fact)
        $allSupportingSources = collect();
        if (!empty($fact->source_id)) {
            $allSupportingSources->push($fact->source_id);
        }

        foreach ($supporting as $ev) {
            if (!$ev->is_syndicated_reprint && !empty($ev->source_id)) {
                $allSupportingSources->push($ev->source_id);
            }
        }

        $independentCount = $allSupportingSources->unique()->count();
        $hasContradiction = $contradicting->isNotEmpty();

        // Status Determination
        $newStatus = $fact->verification_status;

        // If human editor has already confirmed or rejected, preserve unless disputed
        if (!in_array($fact->verification_status, ['verified_by_editor', 'rejected'], true)) {
            if ($hasContradiction) {
                $newStatus = 'conflicting'; // disputed
            } elseif ($independentCount >= 2) {
                $newStatus = 'supported_multiple';
            } elseif ($independentCount === 1) {
                $newStatus = 'supported_single';
            } else {
                $newStatus = 'unverified';
            }
        }

        // 10-Dimension Evaluation
        $dimensions = $this->evaluateTenDimensions($fact, $evidences, $independentCount, $hasContradiction);

        $fact->update([
            'verification_status' => $newStatus,
            'confidence_score' => $dimensions['overall_confidence'],
        ]);

        return $fact;
    }

    /**
     * Evaluate the 10 verification dimensions.
     *
     * @param Collection<int, ClaimEvidence> $evidences
     * @return array<string, float>
     */
    public function evaluateTenDimensions(
        ExtractedFact $fact,
        Collection $evidences,
        int $independentCount,
        bool $hasContradiction
    ): array {
        $avgReliability = $evidences->avg('reliability_score') ?: 0.70;

        // 1. Source Reliability (0.0 - 1.0)
        $dimReliability = (float) $avgReliability;

        // 2. Source Independence: Penalizes when multiple reports are just syndicated copies
        $totalSupporting = $evidences->where('stance', 'supports')->count();
        $syndicatedCount = $evidences->where('is_syndicated_reprint', true)->count();
        $dimIndependence = $totalSupporting > 0
            ? max(0.2, (float) (($totalSupporting - $syndicatedCount) / $totalSupporting))
            : 0.5;

        // 3. Evidence Quality: Checks length, presence of quotes or specific data
        $evidenceLength = strlen($fact->evidence_excerpt ?? '');
        $hasQuotes = str_contains($fact->evidence_excerpt ?? '', '"') || str_contains($fact->evidence_excerpt ?? '', '“');
        $dimQuality = min(1.0, 0.5 + ($evidenceLength > 100 ? 0.3 : 0.1) + ($hasQuotes ? 0.2 : 0.0));

        // 4. Publication Recency
        $dimRecency = 0.90;

        // 5. Reporting Consistency: drops significantly if contradicting reports exist
        $dimConsistency = $hasContradiction ? 0.35 : ($independentCount >= 2 ? 0.95 : 0.75);

        // 6. Historical Consistency
        $dimHistorical = 0.85;

        // 7. Entity Matching Accuracy
        $dimEntityMatch = !empty($fact->entity) ? 0.90 : 0.60;

        // 8. Numerical Consistency: checks if numeric assertions are present
        $hasNumbers = (bool) preg_match('/\d+/', $fact->claim);
        $dimNumerical = $hasNumbers ? 0.85 : 0.95;

        // 9. Attribution Accuracy
        $dimAttribution = !empty($fact->source_url) ? 0.95 : 0.70;

        // 10. Uncertainty: lower score means less uncertainty (hedging words reduce certainty)
        $hedgingWords = ['allegedly', 'reportedly', 'unconfirmed', 'sources claim', 'rumored'];
        $foundHedging = 0;
        foreach ($hedgingWords as $hedge) {
            if (stripos($fact->claim, $hedge) !== false) {
                $foundHedging++;
            }
        }
        $dimUncertainty = min(1.0, 0.10 + ($foundHedging * 0.25));

        $overallConfidence = (
            ($dimReliability * 0.20) +
            ($dimIndependence * 0.20) +
            ($dimConsistency * 0.25) +
            ($dimQuality * 0.15) +
            ($dimAttribution * 0.10) +
            ((1.0 - $dimUncertainty) * 0.10)
        );

        return [
            'source_reliability' => round($dimReliability, 2),
            'source_independence' => round($dimIndependence, 2),
            'evidence_quality' => round($dimQuality, 2),
            'publication_recency' => round($dimRecency, 2),
            'reporting_consistency' => round($dimConsistency, 2),
            'historical_consistency' => round($dimHistorical, 2),
            'entity_matching' => round($dimEntityMatch, 2),
            'numerical_consistency' => round($dimNumerical, 2),
            'attribution_accuracy' => round($dimAttribution, 2),
            'uncertainty_score' => round($dimUncertainty, 2),
            'overall_confidence' => round(min(0.99, max(0.10, $overallConfidence)), 2),
        ];
    }

    /**
     * Record an explicit reviewer decision (editor-verified, disputed, rejected).
     */
    public function recordReviewerDecision(
        ExtractedFact $fact,
        string $status,
        ?string $reason = null,
        ?string $userId = null
    ): ExtractedFact {
        return DB::transaction(function () use ($fact, $status, $reason, $userId) {
            $prev = $fact->verification_status;

            $fact->update([
                'verification_status' => $status,
                'editorial_notes' => $reason,
                'verified_by_user_id' => $userId,
                'verified_at' => now(),
            ]);

            FactVerificationHistory::create([
                'fact_id' => $fact->id,
                'user_id' => $userId,
                'action' => 'reviewer_decision',
                'previous_status' => $prev,
                'new_status' => $status,
                'editorial_reason' => $reason,
                'dimension_scores' => $this->evaluateTenDimensions(
                    $fact,
                    $fact->evidences,
                    $fact->evidences->where('stance', 'supports')->count(),
                    $fact->evidences->where('stance', 'contradicts')->isNotEmpty()
                ),
            ]);

            return $fact;
        });
    }

    /**
     * Measure text similarity between two evidence snippets.
     */
    protected function calculateTextSimilarity(string $t1, string $t2): float
    {
        $str1 = strtolower(trim(preg_replace('/\s+/', ' ', $t1)));
        $str2 = strtolower(trim(preg_replace('/\s+/', ' ', $t2)));

        if ($str1 === $str2) {
            return 1.0;
        }

        $tokens1 = array_unique(explode(' ', $str1));
        $tokens2 = array_unique(explode(' ', $str2));

        $intersection = count(array_intersect($tokens1, $tokens2));
        $union = count(array_unique(array_merge($tokens1, $tokens2)));

        return $union > 0 ? ($intersection / $union) : 0.0;
    }
}
