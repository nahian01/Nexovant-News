<?php

declare(strict_types=1);

namespace App\Services\Notification\Adapters;

use App\Mail\NexovantNotificationMail;
use App\Models\Notification;
use App\Models\NotificationDeliveryLog;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailChannelAdapter implements ChannelAdapterInterface
{
    public function getChannelName(): string
    {
        return 'email';
    }

    public function isConfigured(): bool
    {
        $mailer = Config::get('mail.default');
        if ($mailer !== 'smtp') {
            return false;
        }

        $smtpHost = Config::get('mail.mailers.smtp.host');
        $username = Config::get('mail.mailers.smtp.username');

        // Check if real authenticated outbound mail provider is configured
        return !empty($smtpHost) && !in_array($smtpHost, ['mailpit', 'localhost', '127.0.0.1', '']) && !empty($username);
    }

    public function send(Notification $notification, User $recipient, array $context = []): array
    {
        if (empty($recipient->email)) {
            $log = NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'channel' => 'email',
                'status' => 'failed',
                'attempt_count' => 1,
                'recipient' => $recipient->id,
                'error_message' => 'Recipient user has no registered email address',
                'sent_at' => now(),
            ]);

            return ['status' => 'failed', 'error' => 'Missing email address'];
        }

        if (!$this->isConfigured()) {
            $log = NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'channel' => 'email',
                'status' => 'unconfigured_channel',
                'attempt_count' => 0,
                'recipient' => $recipient->email,
                'error_message' => 'Outbound SMTP/SES provider not configured in application environment.',
                'sent_at' => null,
            ]);

            return [
                'status' => 'unconfigured_channel',
                'error' => 'Outbound SMTP provider not configured in application environment',
            ];
        }

        try {
            Mail::to($recipient->email)->send(new NexovantNotificationMail($notification, $recipient));

            $log = NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'channel' => 'email',
                'status' => 'delivered',
                'attempt_count' => 1,
                'recipient' => $recipient->email,
                'sent_at' => now(),
            ]);

            return [
                'status' => 'delivered',
                'message_id' => $log->id,
            ];
        } catch (\Throwable $e) {
            Log::warning('Email delivery failed', [
                'notification_id' => $notification->id,
                'recipient' => $recipient->email,
                'error' => $e->getMessage(),
            ]);

            $log = NotificationDeliveryLog::create([
                'notification_id' => $notification->id,
                'channel' => 'email',
                'status' => 'failed',
                'attempt_count' => 1,
                'recipient' => $recipient->email,
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
