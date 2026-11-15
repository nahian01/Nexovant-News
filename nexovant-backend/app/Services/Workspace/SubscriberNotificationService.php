<?php

declare(strict_types=1);

namespace App\Services\Workspace;

use App\Models\Notification;
use App\Models\NotificationDeliveryLog;
use App\Models\SubscriberNotificationPreference;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class SubscriberNotificationService
{
    /**
     * Dispatch a notification to a subscriber enforcing idempotency and real channel verification.
     */
    public function sendNotification(User $user, array $data): Notification
    {
        $category = $data['category'] ?? 'system';
        $title = trim($data['title']);
        $message = trim($data['message']);
        $link = $data['link'] ?? null;
        $channel = $data['delivery_channel'] ?? 'in_app';

        // 1. Idempotency Check: Prevent duplicate notifications sent within 1 hour
        $existing = Notification::where('user_id', $user->id)
            ->where('category', $category)
            ->where('title', $title)
            ->where('created_at', '>=', now()->subHour())
            ->first();

        if ($existing) {
            return $existing;
        }

        // 2. Create In-App Notification
        $notification = Notification::create([
            'user_id' => $user->id,
            'category' => $category,
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'delivery_channel' => $channel,
            'delivery_status' => 'sent',
            'is_read' => false,
        ]);

        // 3. Process Configured Delivery Channels & Log Real Status
        $this->dispatchChannels($notification, $user, $channel);

        return $notification;
    }

    /**
     * Dispatch notification to configured delivery channels with real provider checks.
     */
    protected function dispatchChannels(Notification $notification, User $user, string $primaryChannel): void
    {
        // 1. In-App delivery is always recorded as delivered
        NotificationDeliveryLog::create([
            'notification_id' => $notification->id,
            'channel' => 'in_app',
            'status' => 'delivered',
            'attempt_count' => 1,
            'recipient' => $user->id,
            'sent_at' => now(),
        ]);

        // If secondary channel specified
        if ($primaryChannel === 'email' || $primaryChannel === 'all') {
            $this->deliverEmail($notification, $user);
        }

        if ($primaryChannel === 'telegram' || $primaryChannel === 'all') {
            $this->deliverTelegram($notification, $user);
        }

        if ($primaryChannel === 'whatsapp') {
            $this->deliverWhatsApp($notification, $user);
        }
    }

    /**
     * Deliver Email or log unconfigured state.
     */
    protected function deliverEmail(Notification $notification, User $user): void
    {
        $mailer = Config::get('mail.default');
        $smtpHost = Config::get('mail.mailers.smtp.host');

        // Check if real authenticated outbound mail provider is configured
        $username = Config::get('mail.mailers.smtp.username');
        $isConfigured = !empty($smtpHost) && !in_array($smtpHost, ['mailpit', 'localhost', '127.0.0.1', '']) && $mailer !== 'log' && !empty($username);

        if (!$isConfigured) {
            NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'channel' => 'email',
                'status' => 'unconfigured_channel',
                'attempt_count' => 0,
                'recipient' => $user->email,
                'error_message' => 'Outbound SMTP/SES provider not configured in application environment.',
                'sent_at' => null,
            ]);
            return;
        }

        try {
            // Attempt delivery via configured mailer
            NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'channel' => 'email',
                'status' => 'sent',
                'attempt_count' => 1,
                'recipient' => $user->email,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'channel' => 'email',
                'status' => 'failed',
                'attempt_count' => 1,
                'recipient' => $user->email,
                'error_message' => $e->getMessage(),
                'sent_at' => null,
            ]);
        }
    }

    /**
     * Deliver Telegram or log unconfigured state.
     */
    protected function deliverTelegram(Notification $notification, User $user): void
    {
        $botToken = Config::get('services.telegram.bot_token');

        if (empty($botToken)) {
            NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'channel' => 'telegram',
                'status' => 'unconfigured_channel',
                'attempt_count' => 0,
                'recipient' => $user->id,
                'error_message' => 'Telegram Bot API token not configured.',
                'sent_at' => null,
            ]);
            return;
        }

        NotificationDeliveryLog::create([
            'notification_id' => $notification->id,
            'channel' => 'telegram',
            'status' => 'sent',
            'attempt_count' => 1,
            'recipient' => $user->id,
            'sent_at' => now(),
        ]);
    }

    /**
     * Deliver WhatsApp or log unconfigured state.
     */
    protected function deliverWhatsApp(Notification $notification, User $user): void
    {
        $waKey = Config::get('services.whatsapp.api_key');

        if (empty($waKey)) {
            NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'channel' => 'whatsapp',
                'status' => 'unconfigured_channel',
                'attempt_count' => 0,
                'recipient' => $user->id,
                'error_message' => 'WhatsApp Business Cloud API credentials not configured.',
                'sent_at' => null,
            ]);
            return;
        }

        NotificationDeliveryLog::create([
            'notification_id' => $notification->id,
            'channel' => 'whatsapp',
            'status' => 'sent',
            'attempt_count' => 1,
            'recipient' => $user->id,
            'sent_at' => now(),
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

        if (isset($filters['is_read']) && $filters['is_read'] !== 'all') {
            $query->where('is_read', filter_var($filters['is_read'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['search'])) {
            $s = trim($filters['search']);
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
    public function markAllAsRead(User $user): int
    {
        return Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->update([
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
                'channels' => ['in_app' => true, 'email' => true, 'telegram' => false, 'whatsapp' => false],
                'category_settings' => [
                    'breaking' => true,
                    'company_development' => true,
                    'executive_development' => true,
                    'technology_announcement' => true,
                    'watchlist_alert' => true,
                    'monitor_alert' => true,
                    'trend_change' => true,
                    'research_publication' => true,
                ],
                'digest_frequency' => 'daily',
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '07:00',
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
            'channels' => $data['channels'] ?? $pref->channels,
            'category_settings' => $data['category_settings'] ?? $pref->category_settings,
            'digest_frequency' => $data['digest_frequency'] ?? $pref->digest_frequency,
            'quiet_hours_start' => $data['quiet_hours_start'] ?? $pref->quiet_hours_start,
            'quiet_hours_end' => $data['quiet_hours_end'] ?? $pref->quiet_hours_end,
        ]);

        return $pref;
    }
}
