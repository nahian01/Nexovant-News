<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscriber;

use App\Services\Workspace\WatchlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatchlistController
{
    public function __construct(
        protected WatchlistService $watchlistService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $watchlists = $this->watchlistService->listWatchlists($request->user());

        return response()->json([
            'success' => true,
            'watchlists' => $watchlists,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $watchlist = $this->watchlistService->getWatchlist($request->user(), $id);

        return response()->json([
            'success' => true,
            'watchlist' => $watchlist,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
            'notification_frequency' => ['nullable', 'string', 'in:real_time,daily,weekly,disabled'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $watchlist = $this->watchlistService->createWatchlist($request->user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'Watchlist created successfully.',
            'watchlist' => $watchlist,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_default' => ['nullable', 'boolean'],
            'notification_frequency' => ['nullable', 'string', 'in:real_time,daily,weekly,disabled'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $watchlist = $this->watchlistService->updateWatchlist($request->user(), $id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Watchlist updated successfully.',
            'watchlist' => $watchlist,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->watchlistService->deleteWatchlist($request->user(), $id);

        return response()->json([
            'success' => true,
            'message' => 'Watchlist deleted successfully.',
        ]);
    }

    public function addItem(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'entity_name' => ['required', 'string', 'max:150'],
            'entity_type' => ['nullable', 'string', 'in:company,executive,product,technology,industry,institution,investor,government_org,region,event,topic'],
            'entity_id' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ]);

        $item = $this->watchlistService->addItem($request->user(), $id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Entity added to watchlist.',
            'item' => $item,
        ], 201);
    }

    public function removeItem(Request $request, string $id, string $itemId): JsonResponse
    {
        $this->watchlistService->removeItem($request->user(), $id, $itemId);

        return response()->json([
            'success' => true,
            'message' => 'Entity removed from watchlist.',
        ]);
    }

    public function timeline(Request $request, string $id): JsonResponse
    {
        $limit = (int) $request->query('limit', 20);
        $timeline = $this->watchlistService->getWatchlistTimeline($request->user(), $id, $limit);

        return response()->json([
            'success' => true,
            'timeline' => $timeline,
        ]);
    }

    public function connectedEntities(Request $request, string $id): JsonResponse
    {
        $connected = $this->watchlistService->getConnectedEntities($request->user(), $id);

        return response()->json([
            'success' => true,
            'connected_entities' => $connected,
        ]);
    }
}
