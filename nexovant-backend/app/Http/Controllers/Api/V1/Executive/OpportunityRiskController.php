<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Executive;

use App\Services\Billing\EntitlementService;
use App\Services\Executive\OpportunityRiskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OpportunityRiskController
{
    public function __construct(
        protected OpportunityRiskService $oppRiskService,
        protected EntitlementService $entitlementService
    ) {}

    public function getOpportunities(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['opportunity_type', 'status', 'per_page']);

        $opportunities = $this->oppRiskService->getOpportunities($filters, $user);

        return response()->json([
            'success' => true,
            'data' => $opportunities->items(),
            'meta' => [
                'current_page' => $opportunities->currentPage(),
                'total' => $opportunities->total(),
                'per_page' => $opportunities->perPage(),
                'last_page' => $opportunities->lastPage(),
            ],
        ]);
    }

    public function storeOpportunity(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->entitlementService->hasFeature($user, EntitlementService::FEATURE_OPPORTUNITY_RISK) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Opportunity & risk intelligence requires an active entitlement.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'opportunity_type' => 'required|string|in:' . implode(',', OpportunityRiskService::VALID_OPPORTUNITY_TYPES),
            'category' => 'nullable|string|max:64',
            'affected_entities' => 'nullable|array',
            'geographic_scope' => 'nullable|string|max:64',
            'description' => 'nullable|string',
            'supporting_evidence' => 'nullable|array',
            'potential_impact' => 'nullable|string',
            'likelihood_score' => 'nullable|numeric|min:0|max:1',
            'time_horizon' => 'nullable|string|max:32',
            'assumptions' => 'nullable|array',
            'recommended_monitoring_indicators' => 'nullable|array',
        ]);

        $opp = $this->oppRiskService->recordOpportunity($validated, $user);

        return response()->json([
            'success' => true,
            'message' => 'Intelligence Opportunity recorded.',
            'data' => $opp,
        ], Response::HTTP_CREATED);
    }

    public function updateOpportunityStatus(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'status' => 'required|string|in:detected,evaluating,pursuing,archived,dismissed',
        ]);

        $opp = $this->oppRiskService->updateOpportunityStatus($id, $validated['status'], $user);

        return response()->json([
            'success' => true,
            'message' => 'Opportunity status updated.',
            'data' => $opp,
        ]);
    }

    public function getRisks(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['risk_type', 'severity', 'status', 'per_page']);

        $risks = $this->oppRiskService->getRisks($filters, $user);

        return response()->json([
            'success' => true,
            'data' => $risks->items(),
            'meta' => [
                'current_page' => $risks->currentPage(),
                'total' => $risks->total(),
                'per_page' => $risks->perPage(),
                'last_page' => $risks->lastPage(),
            ],
        ]);
    }

    public function storeRisk(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->entitlementService->hasFeature($user, EntitlementService::FEATURE_OPPORTUNITY_RISK) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Opportunity & risk intelligence requires an active entitlement.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'risk_type' => 'required|string|in:' . implode(',', OpportunityRiskService::VALID_RISK_TYPES),
            'category' => 'nullable|string|max:64',
            'affected_entities' => 'nullable|array',
            'geographic_scope' => 'nullable|string|max:64',
            'description' => 'nullable|string',
            'supporting_evidence' => 'nullable|array',
            'potential_impact' => 'nullable|string',
            'likelihood_score' => 'nullable|numeric|min:0|max:1',
            'severity' => 'nullable|string|in:low,medium,high,critical',
            'time_horizon' => 'nullable|string|max:32',
            'assumptions' => 'nullable|array',
            'recommended_monitoring_indicators' => 'nullable|array',
        ]);

        $risk = $this->oppRiskService->recordRisk($validated, $user);

        return response()->json([
            'success' => true,
            'message' => 'Intelligence Risk recorded.',
            'data' => $risk,
        ], Response::HTTP_CREATED);
    }

    public function updateRiskStatus(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'status' => 'required|string|in:detected,escalating,mitigating,resolved,dismissed',
        ]);

        $risk = $this->oppRiskService->updateRiskStatus($id, $validated['status'], $user);

        return response()->json([
            'success' => true,
            'message' => 'Risk status updated.',
            'data' => $risk,
        ]);
    }
}
