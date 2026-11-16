<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Automation;

use App\Models\IntelligenceSignal;
use App\Models\OrganizationMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SignalController
{
    /**
     * List active intelligence signals with multi-tenant filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = null;

        if ($user) {
            $membership = OrganizationMember::where('user_id', $user->id)->first();
            $tenantId = $membership?->organization_id ?? $user->organization_id;
        }

        $query = IntelligenceSignal::with(['evidences', 'earlyWarning'])
            ->latest('first_observed_at');

        // Multi-tenant isolation: public global signals + user's organization signals
        if ($tenantId !== null) {
            $query->where(function ($q) use ($tenantId) {
                $q->whereNull('organization_id')
                  ->orWhere('organization_id', $tenantId);
            });
        } else {
            $query->whereNull('organization_id');
        }

        // Filters
        if ($request->filled('category') && $request->input('category') !== 'all') {
            $query->where('signal_category', $request->input('category'));
        }

        if ($request->filled('severity') && $request->input('severity') !== 'all') {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = min(50, max(5, (int) $request->input('per_page', 15)));
        $signals = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $signals->items(),
            'meta' => [
                'current_page' => $signals->currentPage(),
                'last_page' => $signals->lastPage(),
                'total' => $signals->total(),
                'per_page' => $signals->perPage(),
            ],
        ]);
    }

    /**
     * Show detailed intelligence signal with evidence, anomalies, and impact.
     */
    public function show(Request $request, string $slugOrId): JsonResponse
    {
        $user = $request->user();
        $tenantId = null;

        if ($user) {
            $membership = OrganizationMember::where('user_id', $user->id)->first();
            $tenantId = $membership?->organization_id ?? $user->organization_id;
        }

        $signal = IntelligenceSignal::where('id', $slugOrId)
            ->orWhere('slug', $slugOrId)
            ->with(['evidences.source', 'anomalies', 'earlyWarning.histories', 'impactAssessments'])
            ->first();

        if (!$signal) {
            return response()->json(['success' => false, 'message' => 'Intelligence signal not found.'], 404);
        }

        // Strict Tenant Boundary Check
        if ($signal->organization_id !== null && $signal->organization_id !== $tenantId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to organization signal.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $signal,
        ]);
    }
}
