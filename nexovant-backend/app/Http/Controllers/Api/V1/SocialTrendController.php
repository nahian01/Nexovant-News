<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\SocialTrend;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialTrendController
{
    /**
     * List trending topics with cross-platform velocity.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SocialTrend::with(['topic.company:id,name,ticker', 'metrics' => function ($q) {
            $q->latest('recorded_at')->limit(1);
        }])->orderByDesc('composite_trend_score');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($timeWindow = $request->query('time_window')) {
            if ($timeWindow === '24h') {
                $query->where('created_at', '>=', now()->subHours(24));
            } elseif ($timeWindow === '7d') {
                $query->where('created_at', '>=', now()->subDays(7));
            } elseif ($timeWindow === '30d') {
                $query->where('created_at', '>=', now()->subDays(30));
            }
        }

        $trends = $query->paginate((int) $request->query('per_page', 15));

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
