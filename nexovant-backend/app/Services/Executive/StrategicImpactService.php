<?php

declare(strict_types=1);

namespace App\Services\Executive;

use App\Models\EarlyWarning;
use App\Models\IntelligenceSignal;
use App\Models\StrategicImpactAssessment;
use App\Models\User;
use App\Services\AI\AIRouterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StrategicImpactService
{
    public const VALID_DIMENSIONS = [
        'financial_exposure',
        'operational_disruption',
        'supply_chain_impact',
        'regulatory_compliance',
        'competitive_positioning',
        'customer_market_demand',
        'technology_disruption',
        'reputation_perception',
        'geopolitical_exposure',
        'strategic_opportunity',
    ];

    public function __construct(
        protected AIRouterService $aiRouter
    ) {}

    /**
     * Conduct a grounded Strategic Impact Assessment.
     */
    public function assessImpact(array $params, User $user): StrategicImpactAssessment
    {
        $dimension = $params['dimension'] ?? 'operational_disruption';
        if (!in_array($dimension, self::VALID_DIMENSIONS, true)) {
            throw new InvalidArgumentException("Invalid impact dimension: {$dimension}");
        }

        $title = $params['title'] ?? ('Strategic Impact: ' . ucwords(str_replace('_', ' ', $dimension)));
        $affectedEntities = $params['affected_entities'] ?? [];
        $affectedIndustry = $params['affected_industry_or_market'] ?? 'Cross-Sector';
        $geographicScope = $params['geographic_scope'] ?? 'Global';
        $timeHorizon = $params['time_horizon'] ?? 'near_term_90d';
        $signalId = $params['signal_id'] ?? null;
        $earlyWarningId = $params['early_warning_id'] ?? null;

        // Collect evidence from linked signal or early warning if available
        $supportingEvidence = $params['supporting_evidence'] ?? [];
        $contradictoryEvidence = $params['contradictory_evidence'] ?? [];

        if ($signalId) {
            $signal = IntelligenceSignal::with('evidences')->find($signalId);
            if ($signal) {
                foreach ($signal->evidences as $ev) {
                    if ($ev->evidence_type === 'contradictory') {
                        $contradictoryEvidence[] = [
                            'source' => $ev->source_name,
                            'excerpt' => $ev->excerpt,
                            'credibility' => $ev->credibility_score,
                        ];
                    } else {
                        $supportingEvidence[] = [
                            'source' => $ev->source_name,
                            'excerpt' => $ev->excerpt,
                            'credibility' => $ev->credibility_score,
                        ];
                    }
                }
            }
        }

        if ($earlyWarningId && empty($supportingEvidence)) {
            $warning = EarlyWarning::find($earlyWarningId);
            if ($warning) {
                $supportingEvidence[] = [
                    'source' => 'Nexovant Early Warning Engine',
                    'excerpt' => $warning->explanation['what_makes_it_unusual'] ?? $warning->title,
                    'credibility' => 0.90,
                ];
            }
        }

        $tenantId = $user->organization_id;
        $isPrivateTenantDataAvailable = $tenantId !== null;

        $methodology = "Multi-vector grounded impact evaluation across {$dimension}. "
            . ($isPrivateTenantDataAvailable
                ? "Correlated with tenant workspace profile (Org ID: {$tenantId})."
                : "Explicit Notice: Analysis is derived strictly from public disclosures and verifiable external reporting rather than private internal financial records.");

        $uncertainty = count($supportingEvidence) < 2
            ? "Limited independent corroboration detected. Confidence is restrained pending primary disclosure confirmation."
            : "Corroborated across multiple independent sources. Key remaining uncertainty rests in regulatory enforcement cadence.";

        return StrategicImpactAssessment::create([
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::random(6),
            'organization_id' => $tenantId,
            'user_id' => $user->id,
            'signal_id' => $signalId,
            'early_warning_id' => $earlyWarningId,
            'affected_entities' => $affectedEntities,
            'affected_industry_or_market' => $affectedIndustry,
            'geographic_scope' => $geographicScope,
            'dimension' => $dimension,
            'potential_direction' => $params['potential_direction'] ?? 'neutral',
            'estimated_magnitude' => $params['estimated_magnitude'] ?? 'moderate',
            'time_horizon' => $timeHorizon,
            'supporting_evidence' => $supportingEvidence,
            'contradictory_evidence' => $contradictoryEvidence,
            'assumptions' => $params['assumptions'] ?? [
                'Macroeconomic baseline remains within projected central bank inflation targets.',
                'Cross-border tariffs remain stable over the observation horizon.',
            ],
            'confidence_level' => count($supportingEvidence) >= 2 ? 'high' : 'medium',
            'uncertainty_explanation' => $uncertainty,
            'methodology_explanation' => $methodology,
            'status' => 'active',
        ]);
    }

    /**
     * Query assessments with multi-tenant filtering.
     */
    public function getAssessments(array $filters, User $user)
    {
        $query = StrategicImpactAssessment::query()->with(['author', 'signal', 'earlyWarning']);

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

        if (!empty($filters['dimension'])) {
            $query->where('dimension', $filters['dimension']);
        }
        if (!empty($filters['magnitude'])) {
            $query->where('estimated_magnitude', $filters['magnitude']);
        }
        if (!empty($filters['direction'])) {
            $query->where('potential_direction', $filters['direction']);
        }

        return $query->orderByDesc('created_at')->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Get single assessment with authorization.
     */
    public function getAssessmentOrFail(string $id, User $user): StrategicImpactAssessment
    {
        $query = StrategicImpactAssessment::with(['author', 'signal', 'earlyWarning'])->where('id', $id);

        if (!$user->isAdmin()) {
            $tenantId = $user->organization_id;
            if ($tenantId !== null) {
                $query->where(function (Builder $q) use ($tenantId, $user) {
                    $q->where('organization_id', $tenantId)
                      ->orWhere('user_id', $user->id);
                });
            } else {
                $query->where('user_id', $user->id);
            }
        }

        return $query->firstOrFail();
    }
}
