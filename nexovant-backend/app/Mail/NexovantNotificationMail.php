<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;

class NexovantNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Notification $notification,
        public readonly User $recipient,
    ) {}

    public function envelope(): Envelope
    {
        $prefix = match ($this->notification->priority) {
            'urgent' => '[URGENT] ',
            'high' => '[ALERT] ',
            default => '',
        };

        return new Envelope(
            subject: $prefix . $this->notification->title . ' — Nexovant Intelligence',
        );
    }

    public function content(): Content
    {
        $appUrl = rtrim((string) Config::get('app.url', 'http://localhost:3000'), '/');
        $preferencesUrl = $appUrl . '/workspace/notifications';
        $unsubscribeUrl = $appUrl . '/workspace/notifications?action=unsubscribe&user=' . urlencode($this->recipient->id);

        $actionUrl = $this->notification->link;
        if (!empty($actionUrl) && !str_starts_with($actionUrl, 'http://') && !str_starts_with($actionUrl, 'https://')) {
            $actionUrl = $appUrl . '/' . ltrim($actionUrl, '/');
        }

        return new Content(
            view: 'emails.notification',
            with: [
                'title' => $this->notification->title,
                'notificationMessage' => $this->notification->message,
                'category' => $this->notification->category,
                'priority' => $this->notification->priority,
                'actionUrl' => $actionUrl,
                'metadata' => $this->notification->metadata ?? [],
                'recipientEmail' => $this->recipient->email,
                'appUrl' => $appUrl,
                'preferencesUrl' => $preferencesUrl,
                'unsubscribeUrl' => $unsubscribeUrl,
            ],
        );
    }

    public function headers(): Headers
    {
        $appUrl = rtrim((string) Config::get('app.url', 'http://localhost:3000'), '/');
        $unsubUrl = $appUrl . '/workspace/notifications?action=unsubscribe&user=' . urlencode($this->recipient->id);

        return new Headers(
            text: [
                'List-Unsubscribe' => '<' . $unsubUrl . '>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
                'X-Nexovant-Notification-ID' => $this->notification->id,
                'X-Nexovant-Priority' => $this->notification->priority,
            ],
        );
    }
}
