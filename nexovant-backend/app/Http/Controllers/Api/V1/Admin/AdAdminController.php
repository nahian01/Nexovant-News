<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdAdminController
{
    /**
     * List advertising campaigns, placements, and metrics.
     */
    public function index(Request $request): JsonResponse
    {
        $campaigns = [
            [
                'id' => 'camp-asml-foundry',
                'advertiser' => 'ASML Holding NV',
                'campaign_name' => 'High-NA EUV European Strategic Brief',
                'placement_type' => 'sidebar_executive_brief',
                'budget_usd' => 12000,
                'spend_usd' => 3420,
                'impressions' => 45200,
                'clicks' => 1240,
                'ctr_percentage' => 2.74,
                'status' => 'approved',
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-30',
                'creative_headline' => 'Advancing Sovereign European Lithography Capacity',
                'creative_body' => 'Download the official technical roadmap on 0.55 NA systems deployment.',
                'destination_url' => 'https://asml.com/roadmap',
            ],
            [
                'id' => 'camp-zurich-risk',
                'advertiser' => 'Zurich Financial Services',
                'campaign_name' => 'Q4 Enterprise Supply Chain Resilience Report',
                'placement_type' => 'feed_sponsored_story',
                'budget_usd' => 8500,
                'spend_usd' => 1100,
                'impressions' => 18900,
                'clicks' => 580,
                'ctr_percentage' => 3.06,
                'status' => 'pending',
                'start_date' => '2026-11-15',
                'end_date' => '2026-12-15',
                'creative_headline' => 'Mitigating Geopolitical Exposure Across High-Tech Supply Chains',
                'creative_body' => 'Audited risk scoring framework for Fortune 500 risk management executives.',
                'destination_url' => 'https://zurich.com/supply-chain-risk',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $campaigns,
            'summary' => [
                'total_campaigns' => 2,
                'active_campaigns' => 1,
                'total_impressions_30d' => 64100,
                'average_ctr' => '2.90%',
                'total_revenue_usd' => 4520,
            ],
        ]);
    }

    /**
     * Approve, reject, or pause an advertising campaign.
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:approved,rejected,paused,completed'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Campaign '{$id}' status updated to {$validated['status']}.",
            'status' => $validated['status'],
        ]);
    }
}
