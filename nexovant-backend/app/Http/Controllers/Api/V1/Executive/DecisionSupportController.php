<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Executive;

use App\Services\Billing\EntitlementService;
use App\Services\Executive\DecisionSupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DecisionSupportController
{
    public function __construct(
        protected DecisionSupportService $decisionService,
        protected EntitlementService $entitlementService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['status', 'approval_status', 'per_page']);

        $decisions = $this->decisionService->getDecisions($filters, $user);

        return response()->json([
            'success' => true,
            'data' => $decisions->items(),
            'meta' => [
                'current_page' => $decisions->currentPage(),
                'total' => $decisions->total(),
                'per_page' => $decisions->perPage(),
                'last_page' => $decisions->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->entitlementService->hasFeature($user, EntitlementService::FEATURE_DECISION_SUPPORT) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Executive decision support workspace requires an active Enterprise or Professional subscription.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'business_question' => 'required|string',
            'decision_owner_id' => 'nullable|uuid',
            'deadline' => 'nullable|date',
            'context_and_background' => 'nullable|string',
            'options' => 'nullable|array',
            'criteria' => 'nullable|array',
        ]);

        $decision = $this->decisionService->createDecision($validated, $user);

        return response()->json([
            'success' => true,
            'message' => 'Executive Decision workflow initiated.',
            'data' => $decision,
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $decision = $this->decisionService->getDecisionOrFail($id, $user);

        return response()->json([
            'success' => true,
            'data' => $decision,
        ]);
    }

    public function addOption(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'option_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'advantages' => 'nullable|array',
            'disadvantages' => 'nullable|array',
            'risks' => 'nullable|array',
            'assumptions' => 'nullable|array',
            'evidence_references' => 'nullable|array',
            'expected_outcomes' => 'nullable|string',
            'score' => 'nullable|numeric',
        ]);

        $option = $this->decisionService->addOption($id, $validated, $user);

        return response()->json([
            'success' => true,
            'message' => 'Decision option added.',
            'data' => $option,
        ], Response::HTTP_CREATED);
    }

    public function addMilestone(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'target_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $milestone = $this->decisionService->addMilestone($id, $validated, $user);

        return response()->json([
            'success' => true,
            'message' => 'Milestone created.',
            'data' => $milestone,
        ], Response::HTTP_CREATED);
    }

    public function updateMilestone(Request $request, string $id, string $milestoneId): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'status' => 'required|string|in:pending,in_progress,completed,delayed',
            'notes' => 'nullable|string',
        ]);

        $milestone = $this->decisionService->updateMilestone($milestoneId, $validated['status'], $validated['notes'] ?? null, $user);

        return response()->json([
            'success' => true,
            'message' => 'Milestone updated.',
            'data' => $milestone,
        ]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'selected_option_id' => 'required|uuid',
            'recorded_decision' => 'required|string',
            'approval_action' => 'required|string|in:approved,rejected',
            'next_steps' => 'nullable|string',
        ]);

        $decision = $this->decisionService->recordDecisionApproval(
            $id,
            $validated['selected_option_id'],
            $validated['recorded_decision'],
            $validated['approval_action'],
            $validated['next_steps'] ?? null,
            $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Binding human decision authorized and recorded.',
            'data' => $decision,
        ]);
    }

    public function postReview(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'notes' => 'required|string',
            'changed_assumptions' => 'nullable|array',
            'observed_results_vs_expected' => 'nullable|string',
            'action_taken' => 'required|string|max:64',
        ]);

        $review = $this->decisionService->recordPostDecisionReview(
            $id,
            $validated['notes'],
            $validated['changed_assumptions'] ?? null,
            $validated['observed_results_vs_expected'] ?? null,
            $validated['action_taken'],
            $user
        );

        return response()->json([
            'success' => true,
            'message' => 'Post-decision review logged.',
            'data' => $review,
        ]);
    }
}
