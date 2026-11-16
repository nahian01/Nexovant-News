<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\IntelligenceEntity;
use App\Models\IntelligenceEvent;
use App\Services\Discovery\EntityDiscoveryService;
use App\Services\Research\HistoricalTimelineService;
use App\Services\Research\RelationshipGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntityDiscoveryController
{
    public function __construct(
        protected EntityDiscoveryService $discoveryService,
        protected RelationshipGraphService $graphService,
        protected HistoricalTimelineService $timelineService
    ) {}

    /**
     * Get rich entity discovery profile with verified vs inferred relationships.
     */
    public function profile(Request $request, string $slugOrId): JsonResponse
    {
        $profile = $this->discoveryService->getEntityProfile($slugOrId, $request->user()?->id);

        return response()->json([
            'success' => true,
            'data' => $profile,
        ]);
    }

    /**
     * Get entity multi-level interactive relationship graph.
     */
    public function graph(Request $request, string $entityId): JsonResponse
    {
        $depth = min(3, max(1, (int) $request->input('depth', 2)));
        $graph = $this->graphService->getMultiLevelGraph($entityId, $depth);

        return response()->json([
            'success' => true,
            'data' => $graph,
        ]);
    }

    /**
     * Get chronological timeline for entity.
     */
    public function timeline(Request $request, string $entityId): JsonResponse
    {
        $filters = $request->only(['event_category', 'start_date', 'end_date']);
        $events = $this->timelineService->getTimelineForEntity($entityId, $filters);

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }
}
