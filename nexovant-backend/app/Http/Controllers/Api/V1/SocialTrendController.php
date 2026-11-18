<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\SocialTrend;
use App\Services\Intelligence\TrendingIntelligenceRadarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialTrendController
{
    /**
     * List trending topics with cross-platform velocity.
     */
    public function index(Request $request, TrendingIntelligenceRadarService $radarService): JsonResponse
    {
        // If radar table has zero or very few trends, perform auto-sync on the fly
        if (SocialTrend::count() < 3) {
            $radarService->detectAndSyncTrends($request->query('time_window'));
        }

        $query = SocialTrend::with(['topic.company:id,name,ticker', 'metrics' => function ($q) {
            $q->latest('recorded_at')->limit(1);
        }])->orderByDesc('composite_trend_score');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($industry = $request->query('industry')) {
            $query->whereHas('topic', function ($tq) use ($industry) {
                $tq->where('industry', 'like', "%{$industry}%");
            });
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%")
                  ->orWhereHas('topic', function ($tq) use ($search) {
                      $tq->where('title', 'like', "%{$search}%")
                         ->orWhere('industry', 'like', "%{$search}%");
                  });
            });
        }

        if ($timeWindow = $request->query('time_window')) {
            $windowQuery = clone $query;
            if ($timeWindow === '24h') {
                $windowQuery->where('created_at', '>=', now()->subHours(24));
            } elseif ($timeWindow === '7d') {
                $windowQuery->where('created_at', '>=', now()->subDays(7));
            } elseif ($timeWindow === '30d') {
                $windowQuery->where('created_at', '>=', now()->subDays(30));
            }

            // If the filtered window has data, use it; otherwise fallback gracefully so radar always has content
            if ($windowQuery->count() > 0) {
                $query = $windowQuery;
            }
        }

        $trends = $query->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $trends->items(),
            'meta' => [
                'current_page' => $trends->currentPage(),
                'last_page' => $trends->lastPage(),
                'total' => $trends->total(),
            ]
        ]);
    }

    /**
     * Trigger on-demand radar detection scan across all current intelligence news.
     */
    public function scan(Request $request, TrendingIntelligenceRadarService $radarService): JsonResponse
    {
        $window = $request->input('time_window');
        $trends = $radarService->detectAndSyncTrends($window);

        return response()->json([
            'success' => true,
            'message' => "Trending Intelligence Radar scan completed. {$trends->count()} active trends evaluated.",
            'count' => $trends->count(),
            'data' => $trends->values(),
        ]);
    }

    /**
     * Generate an on-demand customized trend prediction.
     */
    public function customPrediction(Request $request, TrendingIntelligenceRadarService $radarService): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'horizon' => ['nullable', 'string', 'in:30d,90d,180d,1y'],
        ]);

        $trend = $radarService->customPredictiveTrend($validated);

        return response()->json([
            'success' => true,
            'message' => "Custom predictive trend projection generated for '{$trend->title}'.",
            'data' => $trend->load(['topic.company', 'metrics', 'sources']),
        ], 201);
    }

    /**
     * Show trend details, velocity breakdown, and verification records.
     */
    public function show(string $id): JsonResponse
    {
        $trend = SocialTrend::with([
            'topic.company',
            'topic.executive',
            'metrics' => function ($q) {
                $q->latest('recorded_at')->limit(24);
            },
            'sources',
            'verifications.article:id,title,slug,verification_status',
        ])->find($id);

        if (!$trend) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TREND_NOT_FOUND',
                    'message' => 'The specified trend record could not be found.',
                ]
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $trend,
        ]);
    }
}

