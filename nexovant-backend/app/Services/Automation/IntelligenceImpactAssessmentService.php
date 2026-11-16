<?php

declare(strict_types=1);

namespace App\Services\Automation;

use App\Models\EarlyWarning;
use App\Models\IntelligenceImpactAssessment;
use App\Models\IntelligenceSignal;

class IntelligenceImpactAssessmentService
{
    /**
     * Conduct structured multi-dimensional impact assessment for a signal or warning.
     * Strictly avoids defamatory speculation or unsupported criminality allegations.
     */
    public function assessImpact(IntelligenceSignal $signal, ?EarlyWarning $warning = null): IntelligenceImpactAssessment
    {
        $dimension = $this->determinePrimaryDimension($signal->signal_category);
        $severity = match ($signal->severity) {
            'critical' => 'severe',
            'high' => 'significant',
            'medium' => 'moderate',
            default => 'low'
        };

        $timeHorizon = match ($signal->signal_category) {
            'supply_chain_disruption' => 'immediate',
            'regulatory_development' => 'medium_term_6m',
            'technology_breakthrough' => 'long_term_1y_plus',
            default => 'short_term_30d'
        };

        $affectedEntities = $signal->related_entities ?? [];
        $affectedSectors = $signal->related_topics ?? [];

        $supportingEvidence = "Assessment based strictly on verified disclosures from {$signal->independent_sources_count} independent source(s) regarding '{$signal->title}'.";
        
        $consequences = match ($dimension) {
            'technological' => "Potential shifts in component integration timelines, architecture migration schedules, and engineering benchmarks across participating ecosystem foundries.",
            'regulatory' => "Anticipated formal compliance review periods, potential adjustments to cross-border export authorization filings, and sector-wide reporting obligations.",
            'industrial' => "Reconfiguration of tier-1 supply-chain contracts, procurement buffer reallocation, and fab capacity reservation adjustments.",
            default => "Broad operational adjustments observed across monitored organizational portfolios and market participants."
        };

        $alternativeInterpretations = "Observed developments may represent routine defensive positioning, scheduled cyclic contract renewals, or preemptive capital restructuring rather than systemic disruption.";
        $limitations = "Estimates reflect currently accessible public announcements and regulatory dockets. Bilateral private commercial terms and internal yield rates remain non-public.";

        return IntelligenceImpactAssessment::create([
            'signal_id' => $signal->id,
            'early_warning_id' => $warning?->id,
            'dimension' => $dimension,
            'impact_severity' => $severity,
            'time_horizon' => $timeHorizon,
            'affected_entities' => $affectedEntities,
            'affected_sectors' => $affectedSectors,
            'supporting_evidence' => $supportingEvidence,
            'potential_consequences' => $consequences,
            'alternative_interpretations' => $alternativeInterpretations,
            'uncertainty_and_limitations' => $limitations,
        ]);
    }

    protected function determinePrimaryDimension(string $category): string
    {
        return match ($category) {
            'regulatory_development', 'policy_change' => 'regulatory',
            'technology_breakthrough', 'scientific_discovery' => 'technological',
            'supply_chain_disruption' => 'industrial',
            'market_shift' => 'economic',
            'corporate_strategy_change' => 'organizational',
            default => 'industrial'
        };
    }
}
