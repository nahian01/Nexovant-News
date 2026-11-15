<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\OrganizationMember;
use App\Services\RAG\GroundedRagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RagSearchController
{
    public function __construct(
        protected GroundedRagService $ragService
    ) {}

    /**
     * Executive RAG Search & Natural Language Intelligence Interface.
     */
    public function query(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:500'],
            'tenant_id' => ['nullable', 'uuid'],
        ]);

        $user = $request->user();
        $queryText = trim((string) $request->input('query'));

        // Determine tenant isolation scope
        $tenantId = null;
        if ($user) {
            $membership = OrganizationMember::where('user_id', $user->id)->first();
            $tenantId = $membership?->organization_id;

            // If explicit tenant_id provided in request, ensure user belongs to that tenant or is platform admin
            $requestedTenant = $request->input('tenant_id');
            if ($requestedTenant && $user->isAdmin()) {
                $tenantId = $requestedTenant;
            } elseif ($requestedTenant && $requestedTenant === $membership?->organization_id) {
                $tenantId = $requestedTenant;
            }
        }

        $result = $this->ragService->query(
            queryText: $queryText,
            tenantId: $tenantId,
            userId: $user?->id
        );

        return response()->json($result);
    }
}
