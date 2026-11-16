<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\SearchAnalyticsLog;
use App\Models\SearchRelevanceFeedback;
use App\Services\Discovery\SearchAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchAnalyticsAdminController
{
    public function __construct(
        protected SearchAnalyticsService $analyticsService
    ) {}

    /**
     * Search telemetry overview, latency KPIs, top queries, and feedback distribution.
     */
    public function overview(Request $request): JsonResponse
    {
        $overview = $this->analyticsService->getAdminOverview();

        return response()->json([
            'success' => true,
            'data' => $overview,
        ]);
    }

    /**
     * List recent search queries and audit logs.
     */
    public function logs(Request $request): JsonResponse
    {
        $logs = SearchAnalyticsLog::with('user:id,name,email')
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $logs,
        ]);
    }

    /**
     * List unanswered (empty) queries requiring knowledge ingestion.
     */
    public function unanswered(Request $request): JsonResponse
    {
        $unanswered = SearchAnalyticsLog::where('is_empty', true)
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $unanswered,
        ]);
    }

    /**
     * List user relevance feedback reports.
     */
    public function feedback(Request $request): JsonResponse
    {
        $feedback = SearchRelevanceFeedback::with('user:id,name,email')
            ->orderByDesc('created_at')
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $feedback,
        ]);
    }
}
