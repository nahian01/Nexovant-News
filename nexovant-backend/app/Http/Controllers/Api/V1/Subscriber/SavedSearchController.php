<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscriber;

use App\Models\SubscriberSavedSearch;
use App\Services\Discovery\SavedSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedSearchController
{
    public function __construct(
        protected SavedSearchService $savedSearchService
    ) {}

    /**
     * List user's saved searches.
     */
    public function index(Request $request): JsonResponse
    {
        $searches = $this->savedSearchService->listSavedSearches($request->user());

        return response()->json([
            'success' => true,
            'data' => $searches,
        ]);
    }

    /**
     * Store new saved search and monitor rule.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'query' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'filters' => ['nullable', 'array'],
            'alert_frequency' => ['nullable', 'string', 'in:immediate,daily_digest,weekly_digest,disabled'],
            'delivery_channels' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $savedSearch = $this->savedSearchService->createSavedSearch(
            user: $request->user(),
            data: $request->all()
        );

        return response()->json([
            'success' => true,
            'message' => 'Saved search configured and automated monitoring active.',
            'data' => $savedSearch,
        ], 201);
    }

    /**
     * Show single saved search with its preview matches.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $savedSearch = SubscriberSavedSearch::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $matches = $this->savedSearchService->getMatches($savedSearch, 10);

        return response()->json([
            'success' => true,
            'data' => $savedSearch,
            'matches' => $matches,
        ]);
    }

    /**
     * Update saved search.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'query' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'filters' => ['nullable', 'array'],
            'alert_frequency' => ['nullable', 'string', 'in:immediate,daily_digest,weekly_digest,disabled'],
            'delivery_channels' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $updated = $this->savedSearchService->updateSavedSearch(
            user: $request->user(),
            id: $id,
            data: $request->all()
        );

        return response()->json([
            'success' => true,
            'message' => 'Saved search updated successfully.',
            'data' => $updated,
        ]);
    }

    /**
     * Toggle monitoring status.
     */
    public function toggle(Request $request, string $id): JsonResponse
    {
        $toggled = $this->savedSearchService->toggleSavedSearch($request->user(), $id);

        return response()->json([
            'success' => true,
            'message' => $toggled->is_active ? 'Monitoring resumed.' : 'Monitoring paused.',
            'data' => $toggled,
        ]);
    }

    /**
     * Delete saved search.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->savedSearchService->deleteSavedSearch($request->user(), $id);

        return response()->json([
            'success' => true,
            'message' => 'Saved search removed.',
        ]);
    }

    /**
     * View recent matching results.
     */
    public function matches(Request $request, string $id): JsonResponse
    {
        $savedSearch = SubscriberSavedSearch::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $limit = min(30, max(5, (int) $request->input('limit', 15)));
        $matches = $this->savedSearchService->getMatches($savedSearch, $limit);

        return response()->json([
            'success' => true,
            'saved_search' => $savedSearch->name,
            'query' => $savedSearch->query,
            'count' => count($matches),
            'matches' => $matches,
        ]);
    }
}
