<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Automation;

use App\Models\EarlyWarning;
use App\Models\OrganizationMember;
use App\Services\Automation\EarlyWarningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EarlyWarningController
{
    public function __construct(
        protected EarlyWarningService $warningService
    ) {}

    /**
     * List active early warnings.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = null;

        if ($user) {
            $membership = OrganizationMember::where('user_id', $user->id)->first();
            $tenantId = $membership?->organization_id ?? $user->organization_id;
        }

        $query = EarlyWarning::with(['signal.evidences'])
            ->latest('created_at');

        // Multi-tenant scoping
        if ($tenantId !== null) {
            $query->where(function ($q) use ($tenantId) {
                $q->whereNull('organization_id')
                  ->orWhere('organization_id', $tenantId);
            });
        } else {
            $query->whereNull('organization_id');
        }

        if ($request->filled('level') && $request->input('level') !== 'all') {
            $query->where('warning_level', $request->input('level'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        $perPage = min(50, max(5, (int) $request->input('per_page', 15)));
        $warnings = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $warnings->items(),
            'meta' => [
                'current_page' => $warnings->currentPage(),
                'last_page' => $warnings->lastPage(),
                'total' => $warnings->total(),
            ],
        ]);
    }

    /**
     * Show early warning with 9-point explanation and history.
     */
    public function show(Request $request, string $slugOrId): JsonResponse
    {
        $user = $request->user();
        $tenantId = null;

        if ($user) {
            $membership = OrganizationMember::where('user_id', $user->id)->first();
            $tenantId = $membership?->organization_id ?? $user->organization_id;
        }

        $warning = EarlyWarning::where('id', $slugOrId)
            ->orWhere('slug', $slugOrId)
            ->with(['signal.evidences.source', 'histories.user'])
            ->first();

        if (!$warning) {
            return response()->json(['success' => false, 'message' => 'Early warning not found.'], 404);
        }

        if ($warning->organization_id !== null && $warning->organization_id !== $tenantId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to organization early warning.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $warning,
        ]);
    }

    /**
     * Transition warning status with audit logging.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:under_analysis,under_review,awaiting_analyst_review,confirmed,escalated,monitoring,resolved,dismissed,reopened',
            'reason' => 'nullable|string|max:500',
        ]);

        $warning = EarlyWarning::findOrFail($id);

        $user = $request->user();
        $tenantId = null;

        if ($user) {
            $membership = OrganizationMember::where('user_id', $user->id)->first();
            $tenantId = $membership?->organization_id ?? $user->organization_id;
        }

        if ($warning->organization_id !== null && $warning->organization_id !== $tenantId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $updated = $this->warningService->transitionStatus(
            warning: $warning,
            newStatus: (string) $request->input('status'),
            user: $user,
            reason: $request->input('reason')
        );

        return response()->json([
            'success' => true,
            'message' => 'Early warning status transitioned successfully.',
            'data' => $updated,
        ]);
    }
}
