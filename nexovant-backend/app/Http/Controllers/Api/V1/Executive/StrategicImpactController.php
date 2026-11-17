<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Executive;

use App\Services\Billing\EntitlementService;
use App\Services\Executive\StrategicImpactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StrategicImpactController
{
    public function __construct(
        protected StrategicImpactService $impactService,
        protected EntitlementService $entitlementService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['dimension', 'magnitude', 'direction', 'per_page']);

        $assessments = $this->impactService->getAssessments($filters, $user);

        return response()->json([
            'success' => true,
            'data' => $assessments->items(),
            'meta' => [
                'current_page' => $assessments->currentPage(),
                'total' => $assessments->total(),
                'per_page' => $assessments->perPage(),
                'last_page' => $assessments->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->entitlementService->hasFeature($user, EntitlementService::FEATURE_STRATEGIC_IMPACT) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Strategic impact analysis requires an active Enterprise or Professional subscription.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'dimension' => 'required|string|in:' . implode(',', StrategicImpactService::VALID_DIMENSIONS),
            'affected_entities' => 'nullable|array',
            'affected_industry_or_market' => 'nullable|string|max:128',
            'geographic_scope' => 'nullable|string|max:64',
            'potential_direction' => 'nullable|string|in:negative,positive,neutral,bifurcated',
            'estimated_magnitude' => 'nullable|string|in:low,moderate,severe,transformative',
            'time_horizon' => 'nullable|string|in:immediate_30d,near_term_90d,medium_term_12m,long_term_24m_plus',
            'signal_id' => 'nullable|uuid',
            'early_warning_id' => 'nullable|uuid',
            'supporting_evidence' => 'nullable|array',
            'contradictory_evidence' => 'nullable|array',
            'assumptions' => 'nullable|array',
        ]);

        $assessment = $this->impactService->assessImpact($validated, $user);

        return response()->json([
            'success' => true,
            'message' => 'Strategic Impact Assessment completed successfully.',
            'data' => $assessment,
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $assessment = $this->impactService->getAssessmentOrFail($id, $user);

        return response()->json([
            'success' => true,
            'data' => $assessment,
        ]);
    }
}
