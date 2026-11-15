<?php

declare(strict_types=1);

namespace App\Services\Workspace;

use App\Models\Notification;
use App\Models\NotificationDeliveryLog;
use App\Models\SubscriberNotificationPreference;
use App\Models\User;
use App\Services\Notification\DTO\NotificationEventDTO;
use App\Services\Notification\NotificationEngine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SubscriberNotificationService
{
    public function __construct(
        protected NotificationEngine $engine,
    ) {}

    /**
     * Dispatch a notification to a subscriber enforcing central 8-stage pipeline.
     */
    public function sendNotification(User $user, array $data): Notification
    {
        $category = $data['category'] ?? 'system';
        $title = trim((string) $data['title']);
        $message = trim((string) $data['message']);
        $link = $data['link'] ?? null;
        $priority = $data['priority'] ?? 'medium';
        $sourceModule = $data['source_module'] ?? 'workspace';
        $orgId = $data['organization_id'] ?? null;
        $metadata = $data['metadata'] ?? [];

        $dto = new NotificationEventDTO(
            eventType: $data['event_type'] ?? "{$sourceModule}.{$category}_alert",
            sourceModule: $sourceModule,
            recipientIds: [$user->id],
            title: $title,
            message: $message,
            link: $link,
            priority: $priority,
            metadata: $metadata,
            tenantId: $orgId,
            idempotencyKey: $data['idempotency_key'] ?? null,
            category: $category,
        );

        $result = $this->engine->dispatch($dto);

        if (!empty($result['notifications'])) {
            return $result['notifications'][0];
        }

        // Return existing or newly created record if duplicate was suppressed
        return Notification::where('user_id', $user->id)
            ->where('title', $title)
            ->latest()
            ->first() ?? new Notification([
                'user_id' => $user->id,
                'category' => $category,
                'title' => $title,
                'message' => $message,
                'delivery_status' => 'sent',
            ]);
    }

    /**
     * List user notifications with filtering and pagination.
     */
    public function listNotifications(User $user, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Notification::where('user_id', $user->id);

        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['priority']) && $filters['priority'] !== 'all') {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['is_read']) && $filters['is_read'] !== 'all') {
            $query->where('is_read', filter_var($filters['is_read'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['search'])) {
            $s = trim((string) $filters['search']);
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%{$s}%")
                  ->orWhere('message', 'like', "%{$s}%");
            });
        }

        return $query->with('deliveryLogs')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(User $user, string $id): Notification
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return $notification;
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(User $user, ?string $category = null): int
    {
        $query = Notification::where('user_id', $user->id)
            ->where('is_read', false);

        if (!empty($category) && $category !== 'all') {
            $query->where('category', $category);
        }

        return $query->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Archive notification (Soft Delete).
     */
    public function archiveNotification(User $user, string $id): bool
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return (bool) $notification->delete();
    }

    /**
     * Hard delete notification.
     */
    public function deleteNotification(User $user, string $id): bool
    {
        $notification = Notification::withTrashed()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return (bool) $notification->forceDelete();
    }

    /**
     * Get count of unread notifications.
     */
    public function getUnreadCount(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
    }

    /**
     * Get or create subscriber notification preferences.
     */
    public function getPreferences(User $user): SubscriberNotificationPreference
    {
        return SubscriberNotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            [
                'channels' => ['in_app' => true, 'email' => true, 'web_push' => true, 'telegram' => false, 'whatsapp' => false],
                'category_settings' => [
                    'breaking' => true,
                    'company_development' => true,
                    'executive_development' => true,
                    'technology_announcement' => true,
                    'watchlist_alert' => true,
                    'monitor_alert' => true,
                    'trend_change' => true,
                    'research_publication' => true,
                    'billing' => true,
                    'system' => true,
                ],
                'digest_frequency' => 'daily',
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '07:00',
                'timezone' => 'UTC',
            ]
        );
    }

    /**
     * Update notification preferences.
     */
    public function updatePreferences(User $user, array $data): SubscriberNotificationPreference
    {
        $pref = $this->getPreferences($user);

        $pref->update([
            'channels' => array_key_exists('channels', $data) ? $data['channels'] : $pref->channels,
            'category_settings' => array_key_exists('category_settings', $data) ? $data['category_settings'] : $pref->category_settings,
            'digest_frequency' => array_key_exists('digest_frequency', $data) ? $data['digest_frequency'] : $pref->digest_frequency,
            'quiet_hours_start' => array_key_exists('quiet_hours_start', $data) ? $data['quiet_hours_start'] : $pref->quiet_hours_start,
            'quiet_hours_end' => array_key_exists('quiet_hours_end', $data) ? $data['quiet_hours_end'] : $pref->quiet_hours_end,
            'telegram_chat_id' => array_key_exists('telegram_chat_id', $data) ? $data['telegram_chat_id'] : $pref->telegram_chat_id,
            'whatsapp_phone' => array_key_exists('whatsapp_phone', $data) ? $data['whatsapp_phone'] : $pref->whatsapp_phone,
            'timezone' => array_key_exists('timezone', $data) ? $data['timezone'] : $pref->timezone,
        ]);

        return $pref;
    }
}
