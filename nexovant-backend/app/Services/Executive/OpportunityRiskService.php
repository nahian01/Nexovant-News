<?php

declare(strict_types=1);

namespace App\Services\Executive;

use App\Models\IntelligenceOpportunity;
use App\Models\IntelligenceRisk;
use App\Models\User;
use App\Services\AI\AIRouterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

class OpportunityRiskService
{
    public const VALID_OPPORTUNITY_TYPES = [
        'emerging_markets',
        'new_technologies',
        'industry_expansion',
        'competitor_weaknesses',
        'regulatory_openings',
        'supply_chain_alternatives',
        'customer_demand_shift',
        'strategic_partnerships',
        'market_consolidation',
    ];

    public const VALID_RISK_TYPES = [
        'regulatory_changes',
        'geopolitical_instability',
        'supply_chain_disruption',
        'competitor_expansion',
        'technology_obsolescence',
        'cybersecurity_developments',
        'market_contraction',
        'operational_dependencies',
        'reputation_threats',
    ];

    public function __construct(
        protected AIRouterService $aiRouter
    ) {}

    /**
     * Create or detect an Intelligence Opportunity.
     */
    public function recordOpportunity(array $params, User $user): IntelligenceOpportunity
    {
        $type = $params['opportunity_type'] ?? 'emerging_markets';
        if (!in_array($type, self::VALID_OPPORTUNITY_TYPES, true)) {
            throw new InvalidArgumentException("Invalid opportunity type: {$type}");
        }

        $title = $params['title'] ?? ('Opportunity: ' . ucwords(str_replace('_', ' ', $type)));
        $tenantId = $user->organization_id;

        return IntelligenceOpportunity::create([
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::random(6),
            'organization_id' => $tenantId,
            'user_id' => $user->id,
            'opportunity_type' => $type,
            'category' => $params['category'] ?? 'Strategic Growth',
            'affected_entities' => $params['affected_entities'] ?? [],
            'geographic_scope' => $params['geographic_scope'] ?? 'Global',
            'description' => $params['description'] ?? 'Identified strategic growth vector from verified intelligence streams.',
            'supporting_evidence' => $params['supporting_evidence'] ?? [
                ['source' => 'Verified Disclosure', 'note' => 'Market intelligence pattern matching.'],
            ],
            'potential_impact' => $params['potential_impact'] ?? 'Accelerated market penetration and revenue diversification.',
            'likelihood_score' => $params['likelihood_score'] ?? 0.650, // Transparent un-calibrated heuristic score
            'time_horizon' => $params['time_horizon'] ?? 'near_term_90d',
            'confidence' => $params['confidence'] ?? 'medium',
            'assumptions' => $params['assumptions'] ?? [
                'Competitor response remains delayed by at least two quarters.',
            ],
            'recommended_monitoring_indicators' => $params['recommended_monitoring_indicators'] ?? [
                'Monthly commercial trademark filings.',
                'Regional partner distribution announcements.',
            ],
            'status' => 'detected',
        ]);
    }

    /**
     * Create or detect an Intelligence Risk.
     */
    public function recordRisk(array $params, User $user): IntelligenceRisk
    {
        $type = $params['risk_type'] ?? 'supply_chain_disruption';
        if (!in_array($type, self::VALID_RISK_TYPES, true)) {
            throw new InvalidArgumentException("Invalid risk type: {$type}");
        }

        $title = $params['title'] ?? ('Risk: ' . ucwords(str_replace('_', ' ', $type)));
        $tenantId = $user->organization_id;

        return IntelligenceRisk::create([
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::random(6),
            'organization_id' => $tenantId,
            'user_id' => $user->id,
            'risk_type' => $type,
            'category' => $params['category'] ?? 'Operational Risk',
            'affected_entities' => $params['affected_entities'] ?? [],
            'geographic_scope' => $params['geographic_scope'] ?? 'Global',
            'description' => $params['description'] ?? 'Detected elevated threat vector with potential operational or financial consequences.',
            'supporting_evidence' => $params['supporting_evidence'] ?? [
                ['source' => 'Verified Intelligence Anomaly', 'note' => 'Threshold deviation alert.'],
            ],
            'potential_impact' => $params['potential_impact'] ?? 'Potential delivery delay and margin compression.',
            'likelihood_score' => $params['likelihood_score'] ?? 0.720,
            'severity' => $params['severity'] ?? 'medium',
            'time_horizon' => $params['time_horizon'] ?? 'near_term_90d',
            'confidence' => $params['confidence'] ?? 'medium',
            'assumptions' => $params['assumptions'] ?? [
                'Alternative supplier ramp-up duration exceeds 60 days.',
            ],
            'recommended_monitoring_indicators' => $params['recommended_monitoring_indicators'] ?? [
                'Port congestion delay index.',
                'Raw material spot contract pricing.',
            ],
            'status' => 'detected',
        ]);
    }

    /**
     * Query opportunities.
     */
    public function getOpportunities(array $filters, User $user)
    {
        $query = IntelligenceOpportunity::query()->with('author');

        if (!$user->isAdmin()) {
            $tenantId = $user->organization_id;
            if ($tenantId !== null) {
                $query->where(function (Builder $q) use ($tenantId, $user) {
                    $q->where('organization_id', $tenantId)
                      ->orWhere(function ($sub) use ($user) {
                          $sub->whereNull('organization_id')->where('user_id', $user->id);
                      });
                });
            } else {
                $query->where('user_id', $user->id);
            }
        }

        if (!empty($filters['opportunity_type'])) {
            $query->where('opportunity_type', $filters['opportunity_type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Query risks.
     */
    public function getRisks(array $filters, User $user)
    {
        $query = IntelligenceRisk::query()->with('author');

        if (!$user->isAdmin()) {
            $tenantId = $user->organization_id;
            if ($tenantId !== null) {
                $query->where(function (Builder $q) use ($tenantId, $user) {
                    $q->where('organization_id', $tenantId)
                      ->orWhere(function ($sub) use ($user) {
                          $sub->whereNull('organization_id')->where('user_id', $user->id);
                      });
                });
            } else {
                $query->where('user_id', $user->id);
            }
        }

        if (!empty($filters['risk_type'])) {
            $query->where('risk_type', $filters['risk_type']);
        }
        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Update risk status.
     */
    public function updateRiskStatus(string $id, string $status, User $user): IntelligenceRisk
    {
        $risk = IntelligenceRisk::where('id', $id)->firstOrFail();
        if (!$user->isAdmin() && $risk->organization_id !== $user->organization_id && $risk->user_id !== $user->id) {
            abort(403, 'Unauthorized access to risk item.');
        }

        $risk->status = $status;
        $risk->save();
        return $risk;
    }

    /**
     * Update opportunity status.
     */
    public function updateOpportunityStatus(string $id, string $status, User $user): IntelligenceOpportunity
    {
        $opp = IntelligenceOpportunity::where('id', $id)->firstOrFail();
        if (!$user->isAdmin() && $opp->organization_id !== $user->organization_id && $opp->user_id !== $user->id) {
            abort(403, 'Unauthorized access to opportunity item.');
        }

        $opp->status = $status;
        $opp->save();
        return $opp;
    }
}
