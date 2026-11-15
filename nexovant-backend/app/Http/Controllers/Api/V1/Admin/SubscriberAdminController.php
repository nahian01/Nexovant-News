<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\NotificationDeliveryLog;
use App\Models\Plan;
use App\Models\SubscriberLibraryItem;
use App\Models\SubscriberMonitor;
use App\Models\SubscriberWatchlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriberAdminController
{
    /**
     * Aggregate telemetry for subscriber intelligence workspace.
     * Note: Enforces strict subscriber privacy - NO private notes or individual browsing trails exposed.
     */
    public function overview(Request $request): JsonResponse
    {
        $totalWatchlists = SubscriberWatchlist::count();
        $totalMonitors = SubscriberMonitor::count();
        $activeMonitors = SubscriberMonitor::where('is_active', true)->count();
        $totalSavedItems = SubscriberLibraryItem::count();
        $totalNotifications = Notification::count();
        $unreadNotifications = Notification::where('is_read', false)->count();

        // Delivery channel breakdown
        $deliveryCounts = NotificationDeliveryLog::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return response()->json([
            'success' => true,
            'metrics' => [
                'total_watchlists' => $totalWatchlists,
                'total_monitors' => $totalMonitors,
                'active_monitors' => $activeMonitors,
                'total_saved_library_items' => $totalSavedItems,
                'total_notifications_sent' => $totalNotifications,
                'unread_notifications' => $unreadNotifications,
                'delivery_health' => [
                    'delivered' => $deliveryCounts['delivered'] ?? 0,
                    'sent' => $deliveryCounts['sent'] ?? 0,
                    'failed' => $deliveryCounts['failed'] ?? 0,
                    'unconfigured_channel' => $deliveryCounts['unconfigured_channel'] ?? 0,
                ],
            ],
        ]);
    }

    /**
     * Inspect notification delivery logs (failures and channel statuses).
     */
    public function notificationLogs(Request $request): JsonResponse
    {
        $query = NotificationDeliveryLog::with('notification')
            ->orderByDesc('created_at');

        if ($request->query('status') && $request->query('status') !== 'all') {
            $query->where('status', $request->query('status'));
        }

        $logs = $query->paginate(30);

        return response()->json([
            'success' => true,
            'logs' => $logs,
        ]);
    }

    /**
     * Get or update global workspace tier entitlement limits & policies.
     */
    public function policies(Request $request): JsonResponse
    {
        if ($request->isMethod('POST') || $request->isMethod('PUT')) {
            $validated = $request->validate([
                'tier' => ['required', 'string'],
                'max_watchlists' => ['required', 'integer', 'min:1'],
                'max_entities_per_watchlist' => ['required', 'integer', 'min:1'],
                'max_active_monitors' => ['required', 'integer', 'min:1'],
                'daily_research_queries' => ['required', 'integer', 'min:1'],
            ]);

            $plan = Plan::where('slug', $validated['tier'])->first();
            if ($plan) {
                $plan->entitlements()->updateOrCreate(
                    ['feature_key' => 'max_watchlists'],
                    ['value_type' => 'number', 'value' => (string) $validated['max_watchlists']]
                );
                $plan->entitlements()->updateOrCreate(
                    ['feature_key' => 'max_entities_per_watchlist'],
                    ['value_type' => 'number', 'value' => (string) $validated['max_entities_per_watchlist']]
                );
                $plan->entitlements()->updateOrCreate(
                    ['feature_key' => 'max_active_monitors'],
                    ['value_type' => 'number', 'value' => (string) $validated['max_active_monitors']]
                );
                $plan->entitlements()->updateOrCreate(
                    ['feature_key' => 'daily_research_queries'],
                    ['value_type' => 'number', 'value' => (string) $validated['daily_research_queries']]
                );
            }

            AuditLog::create([
                'id' => (string) Str::uuid(),
                'user_id' => $request->user()->id,
                'action' => 'admin.subscriber_policies.update',
                'resource_type' => 'plan_policy',
                'resource_id' => $validated['tier'],
                'metadata' => $validated,
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Workspace policy updated for tier: {$validated['tier']}.",
            ]);
        }

        $plans = Plan::with('entitlements')->get();

        return response()->json([
            'success' => true,
            'plans' => $plans,
        ]);
    }
}
