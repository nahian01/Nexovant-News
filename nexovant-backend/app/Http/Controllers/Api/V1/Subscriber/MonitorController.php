<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscriber;

use App\Services\Workspace\IntelligenceMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitorController
{
    public function __construct(
        protected IntelligenceMonitorService $monitorService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $monitors = $this->monitorService->listMonitors($request->user());

        return response()->json([
            'success' => true,
            'monitors' => $monitors,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $monitor = $this->monitorService->getMonitor($request->user(), $id);

        return response()->json([
            'success' => true,
            'monitor' => $monitor,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'keywords' => ['required', 'array', 'min:1'],
            'excluded_terms' => ['nullable', 'array'],
            'entity_names' => ['nullable', 'array'],
            'categories' => ['nullable', 'array'],
            'industries' => ['nullable', 'array'],
            'regions' => ['nullable', 'array'],
            'min_credibility_score' => ['nullable', 'numeric', 'between:0,1'],
            'require_verified_only' => ['nullable', 'boolean'],
            'alert_frequency' => ['nullable', 'string', 'in:real_time,daily_digest,weekly'],
            'delivery_channels' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $monitor = $this->monitorService->createMonitor($request->user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'Intelligence monitor activated.',
            'monitor' => $monitor,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'keywords' => ['nullable', 'array'],
            'excluded_terms' => ['nullable', 'array'],
            'entity_names' => ['nullable', 'array'],
            'categories' => ['nullable', 'array'],
            'industries' => ['nullable', 'array'],
            'regions' => ['nullable', 'array'],
            'min_credibility_score' => ['nullable', 'numeric', 'between:0,1'],
            'require_verified_only' => ['nullable', 'boolean'],
            'alert_frequency' => ['nullable', 'string', 'in:real_time,daily_digest,weekly'],
            'delivery_channels' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $monitor = $this->monitorService->updateMonitor($request->user(), $id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Monitor rule updated.',
            'monitor' => $monitor,
        ]);
    }

    public function toggle(Request $request, string $id): JsonResponse
    {
        $monitor = $this->monitorService->toggleMonitor($request->user(), $id);

        return response()->json([
            'success' => true,
            'message' => $monitor->is_active ? 'Monitor activated.' : 'Monitor paused.',
            'monitor' => $monitor,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->monitorService->deleteMonitor($request->user(), $id);

        return response()->json([
            'success' => true,
            'message' => 'Monitor deleted.',
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string'],
            'keywords' => ['required', 'array', 'min:1'],
            'excluded_terms' => ['nullable', 'array'],
            'entity_names' => ['nullable', 'array'],
            'require_verified_only' => ['nullable', 'boolean'],
        ]);

        $matches = $this->monitorService->previewMatches($request->user(), $validated);

        return response()->json([
            'success' => true,
            'matches' => $matches,
            'match_count' => count($matches),
        ]);
    }
}
