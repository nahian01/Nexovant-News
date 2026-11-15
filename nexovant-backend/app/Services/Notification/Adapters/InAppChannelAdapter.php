<?php

declare(strict_types=1);

namespace App\Services\Notification\Adapters;

use App\Models\Notification;
use App\Models\NotificationDeliveryLog;
use App\Models\User;

class InAppChannelAdapter implements ChannelAdapterInterface
{
    public function getChannelName(): string
    {
        return 'in_app';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(Notification $notification, User $recipient, array $context = []): array
    {
        // In-App notifications are persisted directly in the notifications table.
        // We log the delivery success to notification_delivery_logs.
        $log = NotificationDeliveryLog::create([
            'notification_id' => $notification->id,
            'channel' => 'in_app',
            'status' => 'delivered',
            'attempt_count' => 1,
            'recipient' => $recipient->id,
            'sent_at' => now(),
        ]);

        return [
            'status' => 'delivered',
            'message_id' => $log->id,
        ];
    }
}
