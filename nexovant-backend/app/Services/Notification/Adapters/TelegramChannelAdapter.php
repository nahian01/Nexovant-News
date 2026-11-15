<?php

declare(strict_types=1);

namespace App\Services\Notification\Adapters;

use App\Models\Notification;
use App\Models\NotificationDeliveryLog;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramChannelAdapter implements ChannelAdapterInterface
{
    public function getChannelName(): string
    {
        return 'telegram';
    }

    public function getBotToken(): ?string
    {
        return Config::get('services.telegram.bot_token') ?: env('TELEGRAM_BOT_TOKEN');
    }

    public function isConfigured(): bool
    {
        return !empty($this->getBotToken());
    }

    public function send(Notification $notification, User $recipient, array $context = []): array
    {
        $botToken = $this->getBotToken();

        // 1. Check if Telegram Bot token is configured on platform
        if (empty($botToken)) {
            $log = NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'channel' => 'telegram',
                'status' => 'unconfigured_channel',
                'attempt_count' => 1,
                'recipient' => $recipient->id,
                'error_message' => 'Telegram Bot API token is not configured on platform (TELEGRAM_BOT_TOKEN)',
                'sent_at' => now(),
            ]);

            return [
                'status' => 'unconfigured_channel',
                'error' => 'Platform Telegram Bot API token not configured',
            ];
        }

        // 2. Check if recipient has linked their Telegram account
        $chatId = $context['chat_id'] ?? $recipient->notificationPreference?->telegram_chat_id;

        if (empty($chatId)) {
            $log = NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'channel' => 'telegram',
                'status' => 'unconfigured_channel',
                'attempt_count' => 1,
                'recipient' => $recipient->id,
                'error_message' => 'Subscriber has not linked their Telegram account in workspace settings',
                'sent_at' => now(),
            ]);

            return [
                'status' => 'unconfigured_channel',
                'error' => 'Subscriber has no linked Telegram chat ID',
            ];
        }

        // 3. Format message
        $priorityPrefix = match ($notification->priority) {
            'urgent' => '🔴 *[URGENT INTEL]*',
            'high' => '🟠 *[HIGH PRIORITY]*',
            'low' => '⚪️ *[UPDATE]*',
            default => '🔵 *[INTELLIGENCE ALERT]*',
        };

        $appUrl = rtrim((string) Config::get('app.url', 'http://localhost:3000'), '/');
        $actionUrl = $notification->link;
        if (!empty($actionUrl) && !str_starts_with($actionUrl, 'http://') && !str_starts_with($actionUrl, 'https://')) {
            $actionUrl = $appUrl . '/' . ltrim($actionUrl, '/');
        }

        $text = "{$priorityPrefix}\n";
        $text .= "*" . addcslashes($notification->title, '_*[]()~`>#+-=|{}.!') . "*\n\n";
        $text .= addcslashes($notification->message, '_*[]()~`>#+-=|{}.!') . "\n\n";
        if (!empty($actionUrl)) {
            $text .= "[View Verified Intelligence](" . $actionUrl . ")\n\n";
        }
        $text .= "_Nexovant — Intelligence Beyond Headlines_";

        try {
            $response = Http::timeout(8)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'MarkdownV2',
                'disable_web_page_preview' => false,
            ]);

            if ($response->successful()) {
                $log = NotificationDeliveryLog::create([
                    'notification_id' => $notification->id,
                    'channel' => 'telegram',
                    'status' => 'delivered',
                    'attempt_count' => 1,
                    'recipient' => (string) $chatId,
                    'sent_at' => now(),
                ]);

                return [
                    'status' => 'delivered',
                    'message_id' => $log->id,
                ];
            }

            $errorMsg = $response->json('description') ?? 'Telegram API error (' . $response->status() . ')';
            $log = NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'channel' => 'telegram',
                'status' => 'failed',
                'attempt_count' => 1,
                'recipient' => (string) $chatId,
                'error_message' => $errorMsg,
                'sent_at' => now(),
            ]);

            return [
                'status' => 'failed',
                'error' => $errorMsg,
            ];
        } catch (\Throwable $e) {
            Log::warning('Telegram delivery exception', ['error' => $e->getMessage()]);

            $log = NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'channel' => 'telegram',
                'status' => 'failed',
                'attempt_count' => 1,
                'recipient' => (string) $chatId,
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
