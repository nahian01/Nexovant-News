<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Executive;

use App\Services\Billing\EntitlementService;
use App\Services\Executive\ExecutiveAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExecutiveAssistantController
{
    public function __construct(
        protected ExecutiveAssistantService $assistantService,
        protected EntitlementService $entitlementService
    ) {}

    public function ask(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$this->entitlementService->hasFeature($user, EntitlementService::FEATURE_EXECUTIVE_ASSISTANT) && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Executive AI assistant requires an active Professional or Enterprise entitlement.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'question' => 'required|string|min:3|max:1000',
        ]);

        $response = $this->assistantService->ask($validated['question'], $user);

        return response()->json([
            'success' => true,
            'data' => $response,
        ]);
    }
}
