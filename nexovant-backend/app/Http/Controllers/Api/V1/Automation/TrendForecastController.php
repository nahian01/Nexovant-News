<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Automation;

use App\Models\OrganizationMember;
use App\Models\TrendForecast;
use App\Services\Automation\PredictiveTrendService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrendForecastController
{
    public function __construct(
        protected PredictiveTrendService $forecastService
    ) {}

    /**
     * List existing trend forecasts.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenantId = null;

        if ($user) {
            $membership = OrganizationMember::where('user_id', $user->id)->first();
            $tenantId = $membership?->organization_id ?? $user->organization_id;
        }

        $query = TrendForecast::latest('created_at');

        if ($tenantId !== null) {
            $query->where(function ($q) use ($tenantId) {
                $q->whereNull('organization_id')->orWhere('organization_id', $tenantId);
            });
        } else {
            $query->whereNull('organization_id');
        }

        if ($request->filled('category') && $request->input('category') !== 'all') {
            $query->where('forecast_category', $request->input('category'));
        }

        $forecasts = $query->limit(20)->get();

        return response()->json([
            'success' => true,
            'data' => $forecasts,
        ]);
    }

    /**
     * Generate or re-calculate a forecast for a subject.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:150',
            'category' => 'nullable|string|in:technology_adoption,capex_expansion,supply_chain_velocity,regulatory_timeline',
            'horizon' => 'nullable|string|in:30d,90d,180d,1y',
        ]);

        $user = $request->user();
        $tenantId = null;

        if ($user) {
            $membership = OrganizationMember::where('user_id', $user->id)->first();
            $tenantId = $membership?->organization_id ?? $user->organization_id;
        }

        $forecast = $this->forecastService->generateForecast(
            subject: (string) $request->input('subject'),
            category: (string) $request->input('category', 'technology_adoption'),
            horizon: (string) $request->input('horizon', '90d'),
            tenantId: $tenantId
        );

        return response()->json([
            'success' => true,
            'forecast' => $forecast,
            'data' => $forecast,
        ]);
    }

    /**
     * Show forecast details with methodology and evaluation metrics.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $tenantId = null;

        if ($user) {
            $membership = OrganizationMember::where('user_id', $user->id)->first();
            $tenantId = $membership?->organization_id ?? $user->organization_id;
        }

        $forecast = TrendForecast::findOrFail($id);

        if ($forecast->organization_id !== null && $forecast->organization_id !== $tenantId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized access to organization trend forecast.'], 403);
        }

        return response()->json([
            'success' => true,
            'forecast' => $forecast,
            'data' => $forecast,
        ]);
    }
}
