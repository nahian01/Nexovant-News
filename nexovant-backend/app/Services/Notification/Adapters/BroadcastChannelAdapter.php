<?php

declare(strict_types=1);

namespace App\Services\Notification\Adapters;

use App\Events\NotificationDispatched;
use App\Models\Notification;
use App\Models\NotificationDeliveryLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class BroadcastChannelAdapter implements ChannelAdapterInterface
{
    public function getChannelName(): string
    {
        return 'broadcast';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(Notification $notification, User $recipient, array $context = []): array
    {
        try {
            event(new NotificationDispatched($notification, $recipient->id));

            $log = NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'channel' => 'broadcast',
                'status' => 'delivered',
                'attempt_count' => 1,
                'recipient' => $recipient->id,
                'sent_at' => now(),
            ]);

            return [
                'status' => 'delivered',
                'message_id' => $log->id,
            ];
        } catch (\Throwable $e) {
            Log::warning('Broadcast notification delivery failed', [
                'notification_id' => $notification->id,
                'user_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);

            $log = NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'channel' => 'broadcast',
                'status' => 'failed',
                'attempt_count' => 1,
                'recipient' => $recipient->id,
                'error_message' => $e->getMessage(),
                'sent_at' => now(),
            ]);

            return [
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }
}
