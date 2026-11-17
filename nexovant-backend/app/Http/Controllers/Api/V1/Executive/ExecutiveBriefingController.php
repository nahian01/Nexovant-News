<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Executive;

use App\Services\Billing\EntitlementService;
use App\Services\Executive\ExecutiveBriefingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExecutiveBriefingController
{
    public function __construct(
        protected ExecutiveBriefingService $briefingService,
        protected EntitlementService $entitlementService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->only(['period_type', 'status', 'per_page']);

        $briefings = $this->briefingService->getBriefings($filters, $user);

        return response()->json([
            'success' => true,
            'data' => $briefings->items(),
            'meta' => [
                'current_page' => $briefings->currentPage(),
                'total' => $briefings->total(),
                'per_page' => $briefings->perPage(),
                'last_page' => $briefings->lastPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Entitlement Check
        if (!$this->entitlementService->hasFeature($user, EntitlementService::FEATURE_EXECUTIVE_BRIEFINGS) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Executive intelligence briefing generation requires an active Professional or Enterprise tier.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'period_type' => 'nullable|string|in:daily,weekly,monthly,custom',
            'coverage_start' => 'nullable|date',
            'coverage_end' => 'nullable|date',
            'industry_scope' => 'nullable|string|max:64',
            'geographic_scope' => 'nullable|string|max:64',
        ]);

        $briefing = $this->briefingService->generateBriefing($validated, $user);

        return response()->json([
            'success' => true,
            'message' => 'Executive Intelligence Briefing synthesized successfully.',
            'data' => $briefing,
        ], Response::HTTP_CREATED);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $briefing = $this->briefingService->getBriefingOrFail($id, $user);

        return response()->json([
            'success' => true,
            'data' => $briefing,
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'executive_summary' => 'nullable|string',
            'what_happened' => 'nullable|array',
            'why_it_matters' => 'nullable|array',
            'what_has_changed' => 'nullable|array',
            'what_could_happen_next' => 'nullable|array',
            'executive_considerations' => 'nullable|array',
            'change_summary' => 'nullable|string|max:255',
        ]);

        $briefing = $this->briefingService->updateBriefing($id, $validated, $user);

        return response()->json([
            'success' => true,
            'message' => 'Briefing updated and version incremented.',
            'data' => $briefing,
        ]);
    }

    public function review(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'status' => 'required|string|in:approved,rejected,reviewed',
            'notes' => 'nullable|string',
        ]);

        $briefing = $this->briefingService->reviewBriefing($id, $validated['status'], $validated['notes'] ?? null, $user);

        return response()->json([
            'success' => true,
            'message' => "Briefing marked as {$validated['status']}.",
            'data' => $briefing,
        ]);
    }

    public function export(Request $request, string $id): mixed
    {
        $user = $request->user();
        $format = $request->query('format', 'markdown');

        $exported = $this->briefingService->exportBriefing($id, $format === 'json' ? 'json' : 'markdown', $user);

        if ($format === 'json') {
            return response()->json($exported);
        }

        return response($exported, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"briefing-{$id}.md\"",
        ]);
    }
}
