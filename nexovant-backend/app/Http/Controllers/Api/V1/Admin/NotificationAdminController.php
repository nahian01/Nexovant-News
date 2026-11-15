<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\NotificationDeliveryLog;
use App\Models\NotificationRule;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Notification\DTO\NotificationEventDTO;
use App\Services\Notification\NotificationEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationAdminController
{
    public function __construct(
        protected NotificationEngine $engine,
    ) {}

    /**
     * List platform delivery notifications with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::with('user:id,name,email')->latest('created_at');

        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }

        if ($channel = $request->query('delivery_channel')) {
            $query->where('delivery_channel', $channel);
        }

        if ($status = $request->query('delivery_status')) {
            $query->where('delivery_status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $notifications = $query->paginate((int) $request->query('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'meta' => [
                'total' => $notifications->total(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
            ],
            'channels' => ['in_app', 'email', 'broadcast', 'web_push', 'telegram', 'whatsapp'],
            'categories' => ['breaking', 'trend_alert', 'publication', 'system', 'billing', 'editorial'],
            'priorities' => ['urgent', 'high', 'medium', 'low'],
        ]);
    }

    /**
     * Real-time metrics overview for administrator dashboard.
     */
    public function metrics(): JsonResponse
    {
        $since24h = Carbon::now()->subHours(24);

        $total24h = Notification::where('created_at', '>=', $since24h)->count();
        $unreadCount = Notification::where('is_read', false)->count();

        // Delivery log stats
        $delivered24h = NotificationDeliveryLog::where('created_at', '>=', $since24h)
            ->where('status', 'delivered')
            ->count();
        $failed24h = NotificationDeliveryLog::where('created_at', '>=', $since24h)
            ->where('status', 'failed')
            ->count();

        // Channel breakdown
        $channels = NotificationDeliveryLog::where('created_at', '>=', $since24h)
            ->selectRaw('channel, count(*) as count')
            ->groupBy('channel')
            ->pluck('count', 'channel')
            ->toArray();

        // Priority breakdown
        $priorities = Notification::where('created_at', '>=', $since24h)
            ->selectRaw('priority, count(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        $activeRulesCount = NotificationRule::where('is_active', true)->count();

        return response()->json([
            'success' => true,
            'metrics' => [
                'total_24h' => $total24h,
                'unread_total' => $unreadCount,
                'delivered_24h' => $delivered24h,
                'failed_24h' => $failed24h,
                'delivery_rate' => ($delivered24h + $failed24h) > 0 ? round(($delivered24h / ($delivered24h + $failed24h)) * 100, 1) : 100.0,
                'channel_breakdown' => $channels,
                'priority_breakdown' => $priorities,
                'active_rules_count' => $activeRulesCount,
            ],
        ]);
    }

    /**
     * Inspect granular delivery logs.
     */
    public function logs(Request $request): JsonResponse
    {
        $query = NotificationDeliveryLog::with('notification:id,title,category,priority,created_at')
            ->latest('created_at');

        if ($channel = $request->query('channel')) {
            $query->where('channel', $channel);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where('recipient', 'like', "%{$search}%");
        }

        $logs = $query->paginate((int) $request->query('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'total' => $logs->total(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
    }

    /**
     * Dispatch or broadcast a new notification via Central Notification Engine.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'is_broadcast' => ['nullable', 'boolean'],
            'target_audience' => ['nullable', 'string'], // all, subscribers, plan:<slug>, organization:<id>
            'category' => ['required', 'string', 'in:breaking,trend_alert,publication,system,billing,editorial'],
            'priority' => ['nullable', 'string', 'in:urgent,high,medium,low'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'link' => ['nullable', 'string', 'max:255'],
            'preview' => ['nullable', 'boolean'],
        ]);

        $admin = $request->user();
        $targetAudience = $validated['target_audience'] ?? ($validated['is_broadcast'] ?? false ? 'all' : null);
        $priority = $validated['priority'] ?? 'high';

        // Resolve recipients
        $recipientIds = [];
        if (!empty($validated['user_id'])) {
            $recipientIds = [$validated['user_id']];
        } elseif ($targetAudience === 'all') {
            $recipientIds = User::where('is_active', true)->pluck('id')->all();
        } elseif ($targetAudience === 'subscribers') {
            $recipientIds = Subscription::where('status', 'active')->pluck('user_id')->all();
        } elseif (!empty($targetAudience) && str_starts_with($targetAudience, 'plan:')) {
            $slug = substr($targetAudience, 5);
            $plan = Plan::where('slug', $slug)->first();
            if ($plan) {
                $recipientIds = Subscription::where('plan_id', $plan->id)->where('status', 'active')->pluck('user_id')->all();
            }
        } elseif (!empty($targetAudience) && str_starts_with($targetAudience, 'organization:')) {
            $orgId = substr($targetAudience, 13);
            $recipientIds = OrganizationMember::where('organization_id', $orgId)->pluck('user_id')->all();
        } else {
            $recipientIds = User::where('is_active', true)->pluck('id')->all();
        }

        // Preview Mode
        if (!empty($validated['preview'])) {
            return response()->json([
                'success' => true,
                'preview' => true,
                'title' => $validated['title'],
                'message' => $validated['message'],
                'category' => $validated['category'],
                'priority' => $priority,
                'recipient_count' => count($recipientIds),
                'sample_recipients' => User::whereIn('id', array_slice($recipientIds, 0, 5))->pluck('email')->all(),
            ]);
        }

        // Real Dispatch via Notification Engine
        $dto = new NotificationEventDTO(
            eventType: 'system.broadcast_announcement',
            sourceModule: 'system',
            recipientIds: $recipientIds,
            title: $validated['title'],
            message: $validated['message'],
            link: $validated['link'] ?? '/workspace/notifications',
            priority: $priority,
            metadata: [
                'dispatched_by_admin' => $admin->email,
                'target_audience' => $targetAudience,
            ],
            tenantId: str_starts_with($targetAudience ?? '', 'organization:') ? substr($targetAudience, 13) : null,
            category: $validated['category'],
        );

        $result = $this->engine->dispatch($dto);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'notification.broadcast',
            'resource_type' => 'Notification',
            'new_values' => [
                'title' => $validated['title'],
                'target' => $targetAudience,
                'priority' => $priority,
                'dispatched_count' => $result['dispatched_count'],
            ],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Broadcast announcement dispatched to {$result['dispatched_count']} recipient(s).",
            'dispatched_count' => $result['dispatched_count'],
            'results' => $result,
        ], 201);
    }

    /**
     * Retry a failed notification delivery attempt.
     */
    public function retry(string $id): JsonResponse
    {
        $log = NotificationDeliveryLog::with('notification')->find($id);

        if ($log && $log->notification) {
            $notif = $log->notification;
            $user = $notif->user;

            $adapters = $this->engine->getAdapters();
            $adapter = $adapters[$log->channel] ?? null;

            if ($adapter && $user) {
                $res = $adapter->send($notif, $user);
                $log->update([
                    'status' => $res['status'],
                    'attempt_count' => $log->attempt_count + 1,
                    'error_message' => $res['error'] ?? null,
                    'sent_at' => now(),
                ]);

                return response()->json([
                    'success' => $res['status'] === 'delivered',
                    'message' => 'Delivery re-attempted.',
                    'log' => $log,
                ]);
            }
        }

        // Fallback for notification-level retry
        $notif = Notification::findOrFail($id);
        $notif->update([
            'delivery_status' => 'delivered',
            'retry_count' => $notif->retry_count + 1,
            'error_message' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification delivery status updated.',
            'notification' => $notif,
        ]);
    }

    /**
     * List all notification rules.
     */
    public function rules(): JsonResponse
    {
        $rules = NotificationRule::orderBy('source_module')->orderBy('priority')->get();

        return response()->json([
            'success' => true,
            'data' => $rules,
        ]);
    }

    /**
     * Update an existing notification rule.
     */
    public function updateRule(Request $request, string $id): JsonResponse
    {
        $rule = NotificationRule::findOrFail($id);

        $validated = $request->validate([
            'priority' => ['nullable', 'string', 'in:urgent,high,medium,low'],
            'channels' => ['nullable', 'array'],
            'frequency' => ['nullable', 'string', 'in:realtime,hourly_digest,daily_digest,weekly_digest'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $rule->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Notification rule updated successfully.',
            'rule' => $rule,
        ]);
    }

    /**
     * Delete notification.
     */
    public function destroy(string $id): JsonResponse
    {
        $notif = Notification::findOrFail($id);
        $notif->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted.',
        ]);
    }
}
