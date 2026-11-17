<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Executive;

use App\Services\Billing\EntitlementService;
use App\Services\Executive\ScenarioAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScenarioAnalysisController
{
    public function __construct(
        protected ScenarioAnalysisService $scenarioService,
        protected EntitlementService $entitlementService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['subject', 'scenario_type', 'is_archived', 'per_page']);

        $scenarios = $this->scenarioService->getScenarios($filters, $user);

        return response()->json([
            'success' => true,
            'data' => $scenarios->items(),
            'meta' => [
                'current_page' => $scenarios->currentPage(),
                'total' => $scenarios->total(),
                'per_page' => $scenarios->perPage(),
                'last_page' => $scenarios->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->entitlementService->hasFeature($user, EntitlementService::FEATURE_SCENARIO_ANALYSIS) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Scenario analysis requires an active Enterprise or Professional subscription.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'scenario_type' => 'required|string|in:optimistic,baseline,adverse,alternative',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'initial_conditions' => 'nullable|string',
            'key_assumptions' => 'nullable|array',
            'trigger_events' => 'nullable|array',
            'influencing_factors' => 'nullable|array',
            'potential_outcomes' => 'nullable|string',
            'risks' => 'nullable|array',
            'opportunities' => 'nullable|array',
            'time_horizon' => 'nullable|string|max:32',
            'indicators_to_monitor' => 'nullable|array',
            'uncertainty_rating' => 'nullable|string|in:low,medium,high,extreme',
            'is_custom' => 'nullable|boolean',
        ]);

        $scenario = $this->scenarioService->createScenario($validated, $user);

        return response()->json([
            'success' => true,
            'message' => 'Scenario modeled successfully.',
            'data' => $scenario,
        ], Response::HTTP_CREATED);
    }

    public function generateSuite(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->entitlementService->hasFeature($user, EntitlementService::FEATURE_SCENARIO_ANALYSIS) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Scenario analysis requires an active Enterprise or Professional subscription.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:255',
        ]);

        $scenarios = $this->scenarioService->generateScenarioSuite($validated['subject'], $user);

        return response()->json([
            'success' => true,
            'message' => 'Scenario suite (Optimistic, Baseline, Adverse) generated.',
            'data' => $scenarios,
        ], Response::HTTP_CREATED);
    }

    public function compare(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'scenario_ids' => 'required|array|min:2',
            'scenario_ids.*' => 'uuid',
        ]);

        $comparison = $this->scenarioService->compareScenarios($validated['scenario_ids'], $user);

        return response()->json([
            'success' => true,
            'data' => $comparison,
        ]);
    }

    public function duplicate(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $copy = $this->scenarioService->duplicateScenario($id, $user);

        return response()->json([
            'success' => true,
            'message' => 'Scenario duplicated for customization.',
            'data' => $copy,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'initial_conditions' => 'nullable|string',
            'key_assumptions' => 'nullable|array',
            'trigger_events' => 'nullable|array',
            'influencing_factors' => 'nullable|array',
            'potential_outcomes' => 'nullable|string',
            'risks' => 'nullable|array',
            'opportunities' => 'nullable|array',
            'indicators_to_monitor' => 'nullable|array',
            'uncertainty_rating' => 'nullable|string|in:low,medium,high,extreme',
        ]);

        $scenario = $this->scenarioService->updateScenario($id, $validated, $user);

        return response()->json([
            'success' => true,
            'message' => 'Scenario updated.',
            'data' => $scenario,
        ]);
    }

    public function archive(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $scenario = $this->scenarioService->archiveScenario($id, $user);

        return response()->json([
            'success' => true,
            'message' => 'Scenario archived.',
            'data' => $scenario,
        ]);
    }
}
