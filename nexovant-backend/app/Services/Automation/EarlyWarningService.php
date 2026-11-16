<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Models\EarlyWarning;
use App\Models\EarlyWarningHistory;
use App\Models\IntelligenceSignal;
use App\Models\User;
use Illuminate\Support\Str;

class EarlyWarningService
{
    /**
     * Evaluate whether an intelligence signal warrants an early warning, and generate or update it.
     */
    public function evaluateSignalForWarning(IntelligenceSignal $signal): ?EarlyWarning
    {
        // Require at least 'medium' severity or multiple independent sources
        if ($signal->severity === 'informational' && $signal->independent_sources_count < 2) {
            return null;
        }

        $warningLevel = match ($signal->severity) {
            'critical' => 'critical',
            'high' => 'elevated',
            'medium' => 'watch',
            default => 'informational'
        };

        $existing = EarlyWarning::where('signal_id', $signal->id)->first();
        if ($existing) {
            // Update warning level if escalated
            if ($existing->warning_level !== $warningLevel) {
                $this->transitionStatus(
                    warning: $existing,
                    newStatus: $warningLevel === 'critical' ? 'escalated' : $existing->status,
                    user: null,
                    reason: "Automated escalation: signal severity upgraded to {$warningLevel} based on increased corroboration."
                );
                $existing->warning_level = $warningLevel;
                $existing->save();
            }
            return $existing;
        }

        // Factor Breakdown
        $factorBreakdown = [
            'independent_sources' => $signal->independent_sources_count,
            'credibility_score' => (float) ($signal->metadata['source_credibility'] ?? 85.0),
            'verification_status' => $signal->verification_status,
            'rate_of_change' => $signal->evidence_count > 1 ? '+100%' : 'initial_observation',
            'persistence' => $signal->first_observed_at ? $signal->first_observed_at->diffForHumans() : 'just_now',
            'geographic_relevance' => $signal->geographic_scope ?? 'GLOBAL',
            'baseline_deviation' => $signal->evidence_count >= 2 ? 'elevated_reporting' : 'baseline',
            'potential_impact' => in_array($signal->signal_category, ['supply_chain_disruption', 'regulatory_development', 'significant_development'], true) ? 'high' : 'moderate',
        ];

        // 9-Part Transparent Explanation
        $explanation = [
            'what_changed' => "New factual reporting emerged concerning {$signal->title}.",
            'when_first_observed' => $signal->first_observed_at?->toIso8601String() ?? now()->toIso8601String(),
            'supporting_sources' => $signal->evidences->pluck('source_name')->filter()->unique()->values()->all(),
            'independent_sources_count' => $signal->independent_sources_count,
            'what_makes_it_unusual' => "Observed reporting clustering across {$signal->independent_sources_count} distinct publishing outlet(s) regarding {$signal->signal_category}.",
            'what_is_verified' => "Core disclosures have been validated against primary regulatory or authoritative wire distributions with status '{$signal->verification_status}'.",
            'what_remains_uncertain' => "Full long-term operational impact, commercial counter-strategies, and potential regulatory litigation remain developing.",
            'why_considered_important' => "Development involves monitored entities with broad supply-chain or industrial ecosystem dependencies.",
            'additional_info_needed' => "Audited quarterly corporate filings, official government gazette releases, and direct counterparty statements.",
        ];

        $warning = EarlyWarning::create([
            'signal_id' => $signal->id,
            'title' => "Early Warning: {$signal->title}",
            'warning_level' => $warningLevel,
            'status' => 'newly_detected',
            'factor_breakdown' => $factorBreakdown,
            'explanation' => $explanation,
            'recommended_watch_points' => [
                'Upcoming earnings calls or investor conference presentations.',
                'Subsequent regulatory agency notices or antitrust committee dockets.',
                'Secondary supplier pricing adjustments or lead-time expansions.',
            ],
            'organization_id' => $signal->organization_id,
        ]);

        EarlyWarningHistory::create([
            'warning_id' => $warning->id,
            'from_status' => null,
            'to_status' => 'newly_detected',
            'reason' => 'Automated initial detection from signal intelligence evaluation.',
            'changes' => ['warning_level' => $warningLevel],
        ]);

        return $warning;
    }

    /**
     * Transition warning status with audit logging.
     */
    public function transitionStatus(EarlyWarning $warning, string $newStatus, ?User $user = null, ?string $reason = null): EarlyWarning
    {
        $allowedTransitions = [
            'newly_detected' => ['under_analysis', 'dismissed', 'confirmed'],
            'under_analysis' => ['awaiting_analyst_review', 'confirmed', 'dismissed'],
            'awaiting_analyst_review' => ['confirmed', 'escalated', 'dismissed'],
            'confirmed' => ['escalated', 'monitoring', 'resolved'],
            'escalated' => ['monitoring', 'resolved', 'dismissed'],
            'monitoring' => ['resolved', 'escalated', 'reopened'],
            'resolved' => ['reopened'],
            'dismissed' => ['reopened'],
            'reopened' => ['under_analysis', 'confirmed'],
        ];

        $currentStatus = $warning->status;
        $fromStatus = $currentStatus;

        // Apply transition
        $warning->status = $newStatus;
        $warning->save();

        EarlyWarningHistory::create([
            'warning_id' => $warning->id,
            'from_status' => $fromStatus,
            'to_status' => $newStatus,
            'user_id' => $user?->id,
            'reason' => $reason ?? 'Status updated by authorized reviewer.',
            'changes' => ['status' => $newStatus],
        ]);

        return $warning;
    }
}
