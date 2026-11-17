<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Executive;

use App\Models\ExecutiveBriefing;
use App\Models\IntelligenceDecision;
use App\Models\IntelligenceOpportunity;
use App\Models\IntelligenceRisk;
use App\Models\IntelligenceScenario;
use App\Models\StrategicImpactAssessment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExecutiveDashboardController
{
    public function cockpit(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = $user->organization_id;

        $tenantScope = function (Builder $query) use ($user, $tenantId) {
            if ($user->isAdmin()) return;
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
        };

        $latestBriefing = ExecutiveBriefing::where($tenantScope)->orderByDesc('created_at')->first();
        $recentImpacts = StrategicImpactAssessment::where($tenantScope)->orderByDesc('created_at')->limit(5)->get();
        $activeRisks = IntelligenceRisk::where($tenantScope)
            ->whereIn('severity', ['high', 'critical'])
            ->whereIn('status', ['detected', 'escalating'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
        $emergingOpportunities = IntelligenceOpportunity::where($tenantScope)
            ->whereIn('status', ['detected', 'evaluating'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
        $activeScenarios = IntelligenceScenario::where($tenantScope)
            ->where('is_archived', false)
            ->orderByDesc('created_at')
            ->limit(4)
            ->get();
        $openDecisions = IntelligenceDecision::where($tenantScope)
            ->whereIn('status', ['draft', 'in_review'])
            ->with(['options', 'decisionOwner'])
            ->orderBy('deadline')
            ->limit(5)
            ->get();

        $metrics = [
            'total_briefings' => ExecutiveBriefing::where($tenantScope)->count(),
            'total_impact_assessments' => StrategicImpactAssessment::where($tenantScope)->count(),
            'total_opportunities' => IntelligenceOpportunity::where($tenantScope)->count(),
            'total_active_risks' => IntelligenceRisk::where($tenantScope)->whereIn('status', ['detected', 'escalating'])->count(),
            'total_scenarios' => IntelligenceScenario::where($tenantScope)->where('is_archived', false)->count(),
            'total_open_decisions' => IntelligenceDecision::where($tenantScope)->whereIn('status', ['draft', 'in_review'])->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'metrics' => $metrics,
                'latest_briefing' => $latestBriefing,
                'recent_impacts' => $recentImpacts,
                'active_risks' => $activeRisks,
                'emerging_opportunities' => $emergingOpportunities,
                'active_scenarios' => $activeScenarios,
                'open_decisions' => $openDecisions,
            ],
        ]);
    }
}
