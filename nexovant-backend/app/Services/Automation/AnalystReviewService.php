<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Models\AnalystReview;
use App\Models\EarlyWarning;
use App\Models\EarlyWarningHistory;
use App\Models\IntelligenceDetectionRule;
use App\Models\IntelligenceSignal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AnalystReviewService
{
    /**
     * Submit human analyst review decision for an intelligence signal.
     */
    public function reviewSignal(
        IntelligenceSignal $signal,
        User $analyst,
        string $decision, // accepted, dismissed, escalated, reassessed, marked_false_positive
        ?string $feedbackNotes = null,
        ?array $addedEvidence = null,
        ?array $suggestedThresholdAdjustments = null
    ): AnalystReview {
        return DB::transaction(function () use ($signal, $analyst, $decision, $feedbackNotes, $addedEvidence, $suggestedThresholdAdjustments) {
            $review = AnalystReview::create([
                'reviewable_type' => 'intelligence_signal',
                'reviewable_id' => $signal->id,
                'analyst_user_id' => $analyst->id,
                'decision' => $decision,
                'feedback_notes' => $feedbackNotes,
                'added_evidence' => $addedEvidence,
                'suggested_threshold_adjustments' => $suggestedThresholdAdjustments,
                'is_applied_to_rules' => false,
            ]);

            // Update signal status based on decision
            $signal->review_status = match ($decision) {
                'accepted' => 'accepted',
                'dismissed', 'marked_false_positive' => 'rejected',
                'escalated' => 'escalated',
                'reassessed' => 'reassessed',
                default => 'pending'
            };

            if (in_array($decision, ['dismissed', 'marked_false_positive'], true)) {
                $signal->status = 'dismissed';
            } elseif ($decision === 'escalated') {
                $signal->status = 'escalated';
                $signal->severity = 'critical';
            } elseif ($decision === 'accepted') {
                $signal->status = 'confirmed';
            }

            $signal->reviewer_id = $analyst->id;
            $signal->review_notes = $feedbackNotes;
            $signal->reviewed_at = now();

            // Append analysis history
            $history = $signal->analysis_history ?? [];
            $history[] = [
                'timestamp' => now()->toIso8601String(),
                'action' => "analyst_review_{$decision}",
                'analyst_id' => $analyst->id,
                'notes' => $feedbackNotes,
            ];
            $signal->analysis_history = $history;
            $signal->save();

            // If linked early warning exists, synchronize status
            if ($signal->earlyWarning) {
                $warningStatus = match ($decision) {
                    'accepted' => 'confirmed',
                    'escalated' => 'escalated',
                    'dismissed', 'marked_false_positive' => 'dismissed',
                    default => $signal->earlyWarning->status
                };

                $signal->earlyWarning->status = $warningStatus;
                $signal->earlyWarning->save();

                EarlyWarningHistory::create([
                    'warning_id' => $signal->earlyWarning->id,
                    'from_status' => $signal->earlyWarning->getOriginal('status'),
                    'to_status' => $warningStatus,
                    'user_id' => $analyst->id,
                    'reason' => "Synchronized from analyst signal review: {$decision}. " . ($feedbackNotes ?? ''),
                ]);
            }

            return $review;
        });
    }

    /**
     * Apply analyst suggested threshold adjustments to detection rules with versioning.
     */
    public function applyRuleAdjustment(IntelligenceDetectionRule $rule, array $newThresholds, User $analyst, string $reason): IntelligenceDetectionRule
    {
        $oldThresholds = $rule->thresholds;
        $trail = $rule->audit_trail ?? [];
        $trail[] = [
            'timestamp' => now()->toIso8601String(),
            'version' => $rule->version,
            'user_id' => $analyst->id,
            'reason' => $reason,
            'previous_thresholds' => $oldThresholds,
            'new_thresholds' => $newThresholds,
        ];

        $rule->thresholds = $newThresholds;
        $rule->version += 1;
        $rule->audit_trail = $trail;
        $rule->save();

        return $rule;
    }
}
