<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscriber;

use App\Services\Workspace\SubscriberResearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriberResearchController
{
    public function __construct(
        protected SubscriberResearchService $researchService
    ) {}

    public function query(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'min:3', 'max:500'],
            'watchlist_id' => ['nullable', 'string'],
        ]);

        $result = $this->researchService->askQuestion(
            $request->user(),
            $validated['query'],
            $validated['watchlist_id'] ?? null
        );

        return response()->json($result);
    }

    public function brief(Request $request, string $watchlistId): JsonResponse
    {
        $result = $this->researchService->generateWatchlistBrief($request->user(), $watchlistId);

        return response()->json($result);
    }
}
