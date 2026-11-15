<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\NotificationDeliveryLog;
use App\Models\NotificationRule;
use App\Models\OrganizationMember;
use App\Models\SubscriberNotificationPreference;
use App\Models\User;
use App\Services\Notification\Adapters\BroadcastChannelAdapter;
use App\Services\Notification\Adapters\ChannelAdapterInterface;
use App\Services\Notification\Adapters\EmailChannelAdapter;
use App\Services\Notification\Adapters\InAppChannelAdapter;
use App\Services\Notification\Adapters\TelegramChannelAdapter;
use App\Services\Notification\Adapters\WebPushChannelAdapter;
use App\Services\Notification\Adapters\WhatsAppChannelAdapter;
use App\Services\Notification\DTO\NotificationEventDTO;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class NotificationEngine
{
    /**
     * @var array<string, ChannelAdapterInterface>
     */
    protected array $adapters = [];

    public function __construct(
        InAppChannelAdapter $inAppAdapter,
        BroadcastChannelAdapter $broadcastAdapter,
        EmailChannelAdapter $emailAdapter,
        WebPushChannelAdapter $webPushAdapter,
        TelegramChannelAdapter $telegramAdapter,
        WhatsAppChannelAdapter $whatsappAdapter,
    ) {
        $this->adapters = [
            'in_app' => $inAppAdapter,
            'broadcast' => $broadcastAdapter,
            'realtime' => $broadcastAdapter,
            'email' => $emailAdapter,
            'web_push' => $webPushAdapter,
            'push' => $webPushAdapter,
            'telegram' => $telegramAdapter,
            'whatsapp' => $whatsappAdapter,
        ];
    }

    /**
     * Get all registered channel adapters.
     *
     * @return array<string, ChannelAdapterInterface>
     */
    public function getAdapters(): array
    {
        return $this->adapters;
    }

    /**
     * Main entry point: Process a domain notification event through the 8-stage pipeline.
     *
     * @return array{
     *     status: string,
     *     event_type: string,
     *     dispatched_count: int,
     *     suppressed_count: int,
     *     notifications: array<int, Notification>,
     *     results: array<string, mixed>
     * }
     */
    public function dispatch(NotificationEventDTO $event): array
    {
        // ---------------------------------------------------------
        // STAGE 1: Ingestion & Idempotency Gate
        // ---------------------------------------------------------
        $idempotencyKey = $event->idempotencyKey ?? $this->computeIdempotencyKey($event);

        if ($this->isDuplicate($idempotencyKey)) {
            Log::info("Notification suppressed by idempotency gate: {$idempotencyKey}");
            return [
                'status' => 'duplicate_suppressed',
                'event_type' => $event->eventType,
                'dispatched_count' => 0,
                'suppressed_count' => count($event->recipientIds),
                'notifications' => [],
                'results' => ['reason' => 'Identical notification processed within deduplication window.'],
            ];
        }

        // ---------------------------------------------------------
        // STAGE 2: Rule Evaluation Engine
        // ---------------------------------------------------------
        $rule = NotificationRule::where('event_type', $event->eventType)
            ->where('is_active', true)
            ->first();

        $priority = $event->priority;
        if ($rule && $rule->priority && $priority === 'medium') {
            $priority = $rule->priority;
        }

        $configuredChannels = $rule && !empty($rule->channels)
            ? (array) $rule->channels
            : ['in_app', 'email', 'broadcast'];

        $category = $event->category ?? $this->resolveCategory($event->sourceModule, $event->eventType);

        // ---------------------------------------------------------
        // STAGE 3: Recipient Resolution & Tenant Isolation
        // ---------------------------------------------------------
        $validRecipients = $this->resolveRecipients($event->recipientIds, $event->tenantId);

        if (empty($validRecipients)) {
            return [
                'status' => 'no_eligible_recipients',
                'event_type' => $event->eventType,
                'dispatched_count' => 0,
                'suppressed_count' => 0,
                'notifications' => [],
                'results' => ['reason' => 'No active or authorized recipients resolved.'],
            ];
        }

        $createdNotifications = [];
        $dispatchSummary = [];
        $dispatchedCount = 0;
        $suppressedCount = 0;

        // ---------------------------------------------------------
        // STAGE 4 - 8: Per-Recipient Pipeline Execution
        // ---------------------------------------------------------
        foreach ($validRecipients as $user) {
            $userPref = $user->notificationPreference;

            // Check quiet hours
            $isQuietHour = $this->isInQuietHours($userPref);
            $isUrgent = ($priority === 'urgent');

            // Category mute check (urgent and billing/security alerts bypass user mute)
            $isCategoryMuted = $this->isCategoryMuted($userPref, $category);
            if ($isCategoryMuted && !$isUrgent && $category !== 'billing' && $category !== 'system') {
                $suppressedCount++;
                continue;
            }

            // Determine active channels for this user
            $activeChannels = $this->resolveActiveChannels($userPref, $configuredChannels, $priority, $category);

            // STAGE 6: Create Persistent In-App Notification
            $notification = Notification::create([
                'user_id' => $user->id,
                'category' => $category,
                'title' => $event->title,
                'message' => $event->message,
                'link' => $event->link,
                'priority' => $priority,
                'source_module' => $event->sourceModule,
                'organization_id' => $event->tenantId,
                'idempotency_key' => $idempotencyKey . ':' . $user->id,
                'metadata' => array_merge($event->metadata, [
                    'event_type' => $event->eventType,
                    'channels_routed' => $activeChannels,
                ]),
                'delivery_channel' => count($activeChannels) > 1 ? 'multi_channel' : ($activeChannels[0] ?? 'in_app'),
                'delivery_status' => 'sent',
                'is_read' => false,
            ]);

            $createdNotifications[] = $notification;
            $userChannelResults = [];

            // If in quiet hours and not urgent, log suppression and defer real-time delivery
            if ($isQuietHour && !$isUrgent) {
                NotificationDeliveryLog::create([
                    'notification_id' => $notification->id,
                    'channel' => 'in_app',
                    'status' => 'delivered',
                    'attempt_count' => 1,
                    'recipient' => $user->id,
                    'sent_at' => now(),
                ]);

                NotificationDeliveryLog::create([
                    'notification_id' => $notification->id,
                    'channel' => 'external_channels',
                    'status' => 'suppressed_quiet_hours',
                    'attempt_count' => 1,
                    'recipient' => $user->id,
                    'error_message' => 'Suppressed during subscriber quiet hours; queued for next digest window.',
                    'sent_at' => now(),
                ]);

                $userChannelResults['quiet_hours'] = 'suppressed_for_digest';
                $dispatchedCount++;
                $dispatchSummary[$user->id] = $userChannelResults;
                continue;
            }

            // STAGE 7: Execute Channel Adapters
            foreach ($activeChannels as $channelKey) {
                $adapter = $this->adapters[$channelKey] ?? null;
                if (!$adapter) {
                    continue;
                }

                $channelResult = $adapter->send($notification, $user, [
                    'event' => $event,
                    'priority' => $priority,
                ]);

                $userChannelResults[$channelKey] = $channelResult['status'];
            }

            $dispatchedCount++;
            $dispatchSummary[$user->id] = $userChannelResults;
        }

        return [
            'status' => 'dispatched',
            'event_type' => $event->eventType,
            'dispatched_count' => $dispatchedCount,
            'suppressed_count' => $suppressedCount,
            'notifications' => $createdNotifications,
            'results' => $dispatchSummary,
        ];
    }

    /**
     * Compute a deterministic idempotency key for deduplication.
     */
    protected function computeIdempotencyKey(NotificationEventDTO $event): string
    {
        $hash = hash('sha256', implode('|', [
            $event->eventType,
            $event->sourceModule,
            $event->title,
            $event->message,
            $event->tenantId ?? 'global',
        ]));

        return "{$event->eventType}:{$hash}";
    }

    /**
     * Check if an event with this idempotency key was processed recently.
     */
    protected function isDuplicate(string $key): bool
    {
        return Notification::where('idempotency_key', 'LIKE', "{$key}%")
            ->where('created_at', '>=', Carbon::now()->subMinutes(30))
            ->exists();
    }

    /**
     * Resolve eligible recipients, strictly verifying multi-tenant organization boundary if applicable.
     *
     * @param string[] $recipientIds
     * @return User[]
     */
    protected function resolveRecipients(array $recipientIds, ?string $tenantId): array
    {
        if (empty($recipientIds)) {
            return [];
        }

        $query = User::with('notificationPreference')->whereIn('id', $recipientIds);

        // Strict Multi-Tenant Enforcement:
        // If tenantId (organization_id) is supplied, only users with an active membership in that organization are eligible.
        if (!empty($tenantId)) {
            $allowedUserIds = OrganizationMember::where('organization_id', $tenantId)
                ->whereIn('user_id', $recipientIds)
                ->pluck('user_id')
                ->all();

            $query->whereIn('id', $allowedUserIds);
        }

        return $query->get()->all();
    }

    /**
     * Check if subscriber is currently within their configured quiet hours.
     */
    public function isInQuietHours(?SubscriberNotificationPreference $pref): bool
    {
        if (!$pref || empty($pref->quiet_hours_start) || empty($pref->quiet_hours_end)) {
            return false;
        }

        $timezone = $pref->timezone ?: 'UTC';

        try {
            $now = Carbon::now($timezone);
            $start = Carbon::createFromTimeString($pref->quiet_hours_start, $timezone);
            $end = Carbon::createFromTimeString($pref->quiet_hours_end, $timezone);

            if ($start->lessThan($end)) {
                return $now->between($start, $end);
            }

            // Overnight quiet hours (e.g., 22:00 to 07:00)
            return $now->greaterThanOrEqualTo($start) || $now->lessThanOrEqualTo($end);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if a category is muted by subscriber preference.
     */
    protected function isCategoryMuted(?SubscriberNotificationPreference $pref, string $category): bool
    {
        if (!$pref || empty($pref->category_settings)) {
            return false;
        }

        $settings = (array) $pref->category_settings;
        if (isset($settings[$category]) && $settings[$category] === false) {
            return true;
        }

        return false;
    }

    /**
     * Resolve active delivery channels considering rule configuration and user preferences.
     *
     * @param string[] $configuredChannels
     * @return string[]
     */
    protected function resolveActiveChannels(
        ?SubscriberNotificationPreference $pref,
        array $configuredChannels,
        string $priority,
        string $category
    ): array {
        // In-app is always enabled
        $channels = ['in_app', 'broadcast'];

        $userEnabledChannels = $pref && !empty($pref->channels)
            ? (array) $pref->channels
            : ['in_app', 'email', 'broadcast', 'web_push'];

        // Urgent/Security/Billing alerts always include email
        if ($priority === 'urgent' || $category === 'billing' || $category === 'system') {
            if (!in_array('email', $channels, true)) {
                $channels[] = 'email';
            }
        }

        foreach ($configuredChannels as $ch) {
            if (!in_array($ch, $channels, true)) {
                // If user has explicitly disabled this channel in preferences, skip it
                if (!empty($userEnabledChannels) && !in_array($ch, $userEnabledChannels, true)) {
                    continue;
                }
                $channels[] = $ch;
            }
        }

        return array_values(array_unique($channels));
    }

    /**
     * Map source module and event type to a standard category.
     */
    protected function resolveCategory(string $sourceModule, string $eventType): string
    {
        if (str_contains($eventType, 'breaking')) {
            return 'breaking';
        }
        if (str_contains($eventType, 'watchlist') || str_contains($eventType, 'entity')) {
            return 'trend_alert';
        }
        if (str_contains($eventType, 'research')) {
            return 'publication';
        }
        if (str_contains($eventType, 'billing') || str_contains($eventType, 'payment') || str_contains($eventType, 'subscription')) {
            return 'billing';
        }
        if (str_contains($eventType, 'organization') || str_contains($eventType, 'team')) {
            return 'editorial';
        }

        return match ($sourceModule) {
            'intelligence' => 'breaking',
            'workspace' => 'trend_alert',
            'research', 'publication' => 'publication',
            'billing', 'subscription' => 'billing',
            'organization' => 'editorial',
            default => 'system',
        };
    }
}
