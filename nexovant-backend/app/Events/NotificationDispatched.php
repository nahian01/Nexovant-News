<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationDispatched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Notification $notification,
        public readonly string $recipientId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->recipientId),
            new Channel('public-user-alerts.' . $this->recipientId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.dispatched';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'title' => $this->notification->title,
            'message' => $this->notification->message,
            'category' => $this->notification->category,
            'priority' => $this->notification->priority,
            'link' => $this->notification->link,
            'source_module' => $this->notification->source_module,
            'metadata' => $this->notification->metadata,
            'created_at' => $this->notification->created_at?->toIso8601String(),
        ];
    }
}
