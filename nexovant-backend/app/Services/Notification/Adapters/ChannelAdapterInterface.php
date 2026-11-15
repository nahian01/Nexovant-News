<?php

declare(strict_types=1);

namespace App\Services\Notification\Adapters;

use App\Models\Notification;
use App\Models\User;

interface ChannelAdapterInterface
{
    /**
     * Get the unique channel identifier (e.g., in_app, email, broadcast, web_push, telegram, whatsapp).
     */
    public function getChannelName(): string;

    /**
     * Check if the channel adapter is actively configured and operational.
     */
    public function isConfigured(): bool;

    /**
     * Send the notification payload to the specified recipient.
     *
     * @param array<string, mixed> $context
     * @return array{status: string, message_id?: string, error?: string}
     */
    public function send(Notification $notification, User $recipient, array $context = []): array;
}
